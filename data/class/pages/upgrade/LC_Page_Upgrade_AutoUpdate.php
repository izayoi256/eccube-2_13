<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2007 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

// {{{ requires
require_once CLASS_PATH . 'pages/upgrade/LC_Page_Upgrade_Base.php';
require_once 'utils/LC_Utils_Upgrade.php';
require_once 'utils/LC_Utils_Upgrade_Log.php';

/**
 * 自動アップデートを行う.
 *
 * TODO 要リファクタリング
 *
 * @package Page
 * @author LOCKON CO.,LTD.
 * @version $Id$
 */
class LC_Page_Upgrade_AutoUpdate extends LC_Page_Upgrade_Base {

    // }}}
    // {{{ functions

    /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        $this->objJson = new Services_Json();
        $this->objLog  = new LC_Utils_Upgrade_Log('Auto Update');

        $this->objForm = new SC_FormParam();
        $this->objForm->addParam('product_id', 'product_id', INT_LEN, '', array('EXIST_CHECK', 'NUM_CHECK', 'MAX_LENGTH_CHECK'));
        $this->objForm->setParam($_POST);
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    function process() {
        $this->objLog->start();

        // IPチェック
        $this->objLog->log('* ip check start');
        if (LC_Utils_Upgrade::isValidIP() !== true) {
            $arrErr = array(
                'status'  => OWNERSSTORE_STATUS_ERROR,
                'errcode' => OWNERSSTORE_ERR_AU_INVALID_IP
            );
            echo $this->objJson->encode($arrErr);
            $this->objLog->errLog($arrErr['errcode'], $_SERVER['REMOTE_ADDR']);
            exit;
        }

        // パラメーチェック
        $this->objLog->log('* post parameter check start');
        if ($this->objForm->checkError()) {
            $arrErr = array(
                'status'  => OWNERSSTORE_STATUS_ERROER,
                'errcode' => OWNERSSTORE_ERR_AU_POST_PARAM
            );
            echo $this->objJson->encode($arrErr);
            $this->objLog->errLog($arrErr['errcode'], $_POST);
            exit;
        }

        // 自動アップデート設定の判定
        $this->objLog->log('* auto update settings check start');
        if ($this->autoUpdateEnable() !== true) {
            $arrErr = array(
                'status'  => OWNERSSTORE_STATUS_ERROER,
                'errcode' => OWNERSSTORE_ERR_AU_NO_UPDATE,
            );
            echo $this->objJson->encode($arrErr);
            $this->objLog->errLog($arrErr['errcode']);
            exit;
        }

        // ダウンロードリクエストを開始
        $this->objLog->log('* http request start');
        $objReq = LC_Utils_Upgrade::request(
            'download',
            array('product_id' => $this->objForm->getValue('product_id'))
        );

        // リクエストのエラーチェック
        $this->objLog->log('* http request check start');
        if (PEAR::isError($objReq)) {
            $arrErr = array(
                'status'  => OWNERSSTORE_STATUS_ERROR,
                'errcode' => OWNERSSTORE_ERR_AU_HTTP_REQ,
            );
            echo $this->objJson->encode($arrErr);
            $this->objLog->errLog($arrErr['errcode'], $objReq);
            exit;
        }

        // レスポンスの検証
        $this->objLog->log('* http response check start');
        if ($objReq->getResponseCode() !== 200) {
            $arrErr = array(
                'status'  => OWNERSSTORE_STATUS_ERROR,
                'errcode' => OWNERSSTORE_ERR_AU_HTTP_RESP_CODE,
            );
            echo $this->objJson->encode($arrErr);
            $this->objLog->errLog($arrErr['errcode'], $objReq);
            exit;
        }

        // JSONデータの検証
        $body = $objReq->getResponseBody();
        $objRet = $this->objJson->decode($body);

        $this->objLog->log('* json data check start');
        if (empty($objRet)) {
            $arrErr = array(
                'status'  => OWNERSSTORE_STATUS_ERROR,
                'errcode' => OWNERSSTORE_ERR_AU_INVALID_JSON_DATA,
            );
            echo $this->objJson->encode($arrErr);
            $this->objLog->errLog($arrErr['errcode'], $objReq);
            exit;
        }
        // ダウンロードデータの保存
        if ($objRet->status === OWNERSSTORE_STATUS_SUCCESS) {
            $this->objLog->log('* save file start');
            $time = time();
            $dir  = DATA_PATH . 'downloads/tmp/';
            $filename = $time . '.tar.gz';

            $data = base64_decode($objRet->body);

            if ($fp = fopen($dir . $filename, "w")) {
                fwrite($fp, $data);
                fclose($fp);
            } else {
                $arrErr = array(
                    'status'  => OWNERSSTORE_STATUS_ERROR,
                    'errcode' => OWNERSSTORE_ERR_AU_FILE_WRITE,
                );
                echo $this->objJson->encode($arrErr);
                $this->objLog->errLog($arrErr['errcode'], $dir . $filename);
                exit;
            }
            // ダウンロードアーカイブを展開する
            $exract_dir = $dir . $time;
            if (!@mkdir($exract_dir)) {
                $arrErr = array(
                    'status'  => OWNERSSTORE_STATUS_ERROR,
                    'errcode' => OWNERSSTORE_ERR_AU_MKDIR,
                );
                echo $this->objJson->encode($arrErr);
                $this->objLog->errLog($arrErr['errcode'], $exract_dir);
                exit;
            }

            $tar = new Archive_Tar($dir . $filename);
            $tar->extract($exract_dir);

            include_once CLASS_PATH . 'batch/SC_Batch_Update.php';
            $objBatch = new SC_Batch_Update();
            $arrCopyLog = $objBatch->execute($exract_dir);

            // テーブルの更新
            $this->updateMdlTable($objRet->product_data);
            // サーバへ通知
            $this->notifyDownload($objReq->getResponseCookies());

            echo $this->objJson->encode(array('status'  => OWNERSSTORE_STATUS_SUCCESS));
            $this->objLog->log('* file save ok');
            exit;
        } else {
            echo $body;
            $this->objLog->errLog($objRet->errcode, array($objRet, $objReq));
            exit;
        }
    }

    /**
     * デストラクタ
     *
     * @return void
     */
    function destroy() {
        $this->objLog->end();
    }

    /**
     * dtb_moduleを更新する
     *
     * @param object $objRet
     */
    function updateMdlTable($objRet) {
        $table = 'dtb_module';
        $where = 'module_id = ?';
        $objQuery = new SC_Query;

        $count = $objQuery->count($table, $where, array($objRet->product_id));
        if ($count) {
            $arrUpdate = array(
                'module_name' => $objRet->name,
                'update_date' => 'NOW()'
            );
            $objQuery->update($table, $arrUpdate ,$where, array($objRet->product_id));
        } else {
            $arrInsert = array(
                'module_id' => $objRet->product_id,
                'module_name' => $objRet->name,
                'auto_update_flg' => '0',
                'create_date'     => 'NOW()',
                'update_date' => 'NOW()'
            );
            $objQuery->insert($table, $arrInsert);
        }
    }

    /**
     * 配信サーバへダウンロード完了を通知する.
     *
     * FIXME エラーコード追加
     * @param array #arrCookies Cookie配列
     * @retrun
     */
    function notifyDownload($arrCookies) {
        $objReq = LC_Utils_Upgrade::request('download_log', array(), $arrCookies);

        return true;
    }

    /**
     * 自動アップデートが有効かどうかを判定する.
     *
     * @return boolean
     */
    function autoUpdateEnable() {
        $product_id = $this->objForm->getValue('product_id');

        $where = 'module_id = ?';
        $objQuery = new SC_Query();
        $arrRet = $objQuery->select('auto_update_flg', 'dtb_module', $where, array($product_id));

        if (isset($arrRet[0]['auto_update_flg'])
        && $arrRet[0]['auto_update_flg'] === '1') {

            return true;
        }

        return false;
    }
}
?>
