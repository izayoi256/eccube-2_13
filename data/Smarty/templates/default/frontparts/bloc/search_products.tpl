<!--{*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2014 LOCKON CO.,LTD. All Rights Reserved.
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
 *}-->

<!--{assign var=limitTo value=5}-->
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.5.0/angular.min.js"></script>
<script type="text/javascript">
    var app = angular.module('myApp', []);
    app.directive('ngDisableUpdown', function(){
        return function(scope, element, attrs){
            element.bind('keydown', function(event){
                if(event.which == 38 || event.which == 40){
                    event.preventDefault();
                }
            })
        }
    });
    app.controller('searchController', function($scope, $filter, $window){
        $scope.products = <!--{$json_arrProducts}-->;
        $scope.applyProduct = function(){
            if($scope.acProduct != null){
                $window.location.href = '<!--{$smarty.const.P_DETAIL_URLPATH}-->' + $scope.acProduct.product_id;
            }
        };
        $scope.isActive = function(product){
            return $scope.acProduct != null && $scope.acProduct == product;
        };
        $scope.changeProduct = function(next){
            var filteredProducts = $filter('limitTo')($filter('filter')($scope.products, $scope.searchProduct), 5);
            if(filteredProducts.length > 0){
                var newIndex = 0;
                var i = next ? 1 : -1;
                if($scope.acProduct != null){
                    newIndex = filteredProducts.indexOf($scope.acProduct) + i;
                }
                if(filteredProducts[newIndex] == null){
                    newIndex = next ? 0 : filteredProducts.length - 1;
                }
                $scope.acProduct = filteredProducts[newIndex];
            }
        };
        $scope.productKeyDown = function(e){
            switch(e.which){
                // エンター
                case 13:
                    if($scope.acProduct != null){
                        $scope.applyProduct();
                    }
                    break;
                // 上
                case 38:
                    $scope.changeProduct(false);
                    break;
                // 下
                case 40:
                    $scope.changeProduct(true);
                    break;
            }
        };
        $scope.searchProduct = '<!--{$smarty.get.name|h}-->';
    });
</script>
<style type="text/css">
.product-autocomplete{
    position: absolute;
    background: white;
    border: 1px solid #999;
}
.product-autocomplete .active{
    background: #FFF99D;
}
.product-autocomplete a{
    white-space: nowrap;
    display: block;
}
.product-autocomplete li{
    border-top: 1px solid #999;
}
</style>
<!--{strip}-->
    <div class="block_outer" ng-app="myApp">
        <div id="search_area">
        <h2><span class="title"><img src="<!--{$TPL_URLPATH}-->img/title/tit_bloc_search.gif" alt="検索条件" /></span></h2>
            <div class="block_body">
                <!--検索フォーム-->
                <form name="search_form" id="search_form" method="get" action="<!--{$smarty.const.ROOT_URLPATH}-->products/list.php" ng-controller="searchController as searchCtrl">
                    <input type="hidden" name="<!--{$smarty.const.TRANSACTION_ID_NAME}-->" value="<!--{$transactionid}-->" />
                    <dl class="formlist">
                        <dt>商品カテゴリから選ぶ</dt>
                        <dd><input type="hidden" name="mode" value="search" />
                        <select name="category_id" class="box145">
                            <option label="全ての商品" value="">全ての商品</option>
                            <!--{html_options options=$arrCatList selected=$category_id}-->
                        </select>
                        </dd>
                    </dl>
                    <dl class="formlist">
                        <!--{if $arrMakerList}-->
                        <dt>メーカーから選ぶ</dt>
                        <dd><select name="maker_id" class="box145">
                            <option label="全てのメーカー" value="">全てのメーカー</option>
                            <!--{html_options options=$arrMakerList selected=$maker_id}-->
                        </select>
                        </dd>
                    </dl>
                    <dl class="formlist">
                        <!--{/if}-->
                        <dt>商品名を入力</dt>
                        <dd>
                            <input type="text" name="name" class="box140" maxlength="50" value="<!--{$smarty.get.name|h}-->"
                                   ng-focus="searching=true" ng-blur="searching=false" ng-change="acProduct=null"
                                   ng-model="searchProduct" autocomplete="off" ng-keydown="productKeyDown($event);"
                                   ng-keydown="productKeyDown($event);" ng-disable-updown />
                            <ul class="product-autocomplete" ng-show="selecting || searching && searchProduct && (products|filter:searchProduct).length"
                                ng-mouseover="selecting=true" ng-mouseleave="selecting=false">
                                <li ng-repeat="product in products|filter:searchProduct|limitTo:<!--{$limitTo}-->" ng-class="{active:isActive(product)}">
                                    <a href="<!--{$smarty.const.P_DETAIL_URLPATH}-->{{product.product_id}}">
                                        {{product.name}} ({{product.product_code}})
                                    </a>
                                </li>
                            </ul>
                        </dd>
                    </dl>
                    <p class="btn">
                        <input type="image" class="hover_change_image" src="<!--{$TPL_URLPATH}-->img/button/btn_bloc_search.jpg" alt="検索" name="search" ng-disabled="acProduct" />
                    </p>
                </form>
            </div>
        </div>
    </div>
<!--{/strip}-->
