<?php

class cConveadTracker {

    static $MODULE_ID = "platina.conveadtracker";

    static function getVisitorInfo($id) {
        if ($usr = CUser::GetByID($id)) {
            $user = $usr->Fetch();
            
            $visitor_info = array();
            $user["NAME"] && $visitor_info["first_name"] = $user["NAME"];
            $user["LAST_NAME"] && $visitor_info["last_name"] = $user["LAST_NAME"];
            $user["EMAIL"] && $visitor_info["email"] = $user["EMAIL"];
            $user["PERSONAL_PHONE"] && $visitor_info["phone"] = $user["PERSONAL_PHONE"];
            $user["PERSONAL_BIRTHDAY"] && $visitor_info["date_of_birth"] = date('Y-m-d', $user["PERSONAL_BIRTHDAY"]);
            $user["PERSONAL_GENDER"] && $visitor_info["gender"] = ($user["PERSONAL_GENDER"] == "M" ? "male" : "female");
            return $visitor_info;
        } else {
            return false;
        }
    }

    static function getUid($visitor_uid) {
        if($visitor_uid){
            self::resetUid ();
            
            return false;
        }
        
        if (isset($_COOKIE["convead_guest_uid"]))
            return $_COOKIE["convead_guest_uid"];
        else {
            $key = isset($_SESSION["UNIQUE_KEY"]) ? $_SESSION["UNIQUE_KEY"] . time() : time();
            $uid = substr(md5($key), 1, 16);
            @setcookie("convead_guest_uid", $uid, 0, "/");
            return $uid;
        }
    }

    static function resetUid() {
        unset($_COOKIE['convead_guest_uid']);
        @setcookie("convead_guest_uid", "", time() - 3600, "/");
    }

    static function productView($arResult, $user_id = false) {

        
 
        if(class_exists("DataManager"))
            return false;

        if (self::contains($_SERVER["HTTP_USER_AGENT"], "facebook.com")) {
            return;
        }


        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
            return;

        global $APPLICATION;
        global $USER;
        
        $visitor_uid = false;
        if(!$user_id)
            $user_id = $USER->GetID();

        $visitor_info = false;
        if ($user_id && $visitor_info = self::getVisitorInfo($user_id)) {
            $visitor_uid = (int) $user_id;
        }
        $guest_uid = self::getUid($visitor_uid);
        $tracker = new ConveadTracker($api_key, $guest_uid, $visitor_uid, $visitor_info, false, SITE_SERVER_NAME);

        $arProduct = CCatalogProduct::GetByIDEx($arResult["PRODUCT_ID"]);
        if(CCatalogSku::IsExistOffers($arResult["PRODUCT_ID"])){
            $mxResult = CCatalogSKU::GetInfoByProductIBlock(
             $arResult["IBLOCK_ID"]
            );
            
            $sort = array();
            $select = array("ID");
            $properties = CIBlockProperty::GetList(Array("sort"=>"DESC", "name"=>"asc"), 
                Array("ACTIVE"=>"Y", "IBLOCK_ID"=>$mxResult['IBLOCK_ID']/*, "PROPERTY_TYPE"=>"L", "MULTIPLE"=>"N"*/)
                );
            $pow = 0;
            $props = array();
           
            while ($prop_fields = $properties->GetNext())
            {
                //print_r($prop_fields);
                if($prop_fields["PROPERTY_TYPE"] == "L" || $prop_fields["PROPERTY_TYPE"] != "L" && $prop_fields["USER_TYPE_SETTINGS"]){
                    $select[] = "PROPERTY_".(isset($prop_fields["CODE"])?$prop_fields["CODE"]:$prop_fields["ID"]);
                    
                    $ar["MULTIPLIER"] = pow(10, $pow);
                    if($prop_fields["PROPERTY_TYPE"] == "L")
                        $ar["NAME"] = "PROPERTY_".(isset($prop_fields["CODE"])?$prop_fields["CODE"]:$prop_fields["ID"])."_ENUM_ID";
                    else
                        $ar["NAME"] = (isset($prop_fields["CODE"])?$prop_fields["CODE"]:$prop_fields["ID"]);
                    $ar["PROPERTY_TYPE"] = $prop_fields["PROPERTY_TYPE"];
                    $props[] = $ar;

                    $pow++;
                }
            }
            
            $arFilter = array('IBLOCK_ID' => $mxResult['IBLOCK_ID'],'=PROPERTY_'.$mxResult['SKU_PROPERTY_ID'] => $arResult["PRODUCT_ID"]);
            $rsOffersItems = CIBlockElement::GetList(
                $sort,
                $arFilter,
                false,
                false,
                $select
            );
            
            $res = array();
            $num = 0;
            while($t = $rsOffersItems->Fetch()){
                
                foreach($props as $prop){
                    if($prop["PROPERTY_TYPE"] == "L")
                        $p = CIBlockPropertyEnum::GetByID($t[$prop["NAME"]]);
                    else{
                        
                        $filter = array("CODE"=>"COLOR_REF");
                        $p = CIBlockElement::GetProperty(
                                              $mxResult['IBLOCK_ID'],
                                              $t["ID"],
                                              array(),
                                              array("CODE"=>$prop["NAME"])
                                            )->Fetch();
                        
                    }
                    
                    
                    $res[$t["ID"]] += isset($p["SORT"])?$p["SORT"]*$prop["MULTIPLIER"]:0;
                }
                $res[$t["ID"]] += $num;
                $num++;
            }

            
            asort($res);
            
            foreach($res as $k=>$v){
                $arResult["PRODUCT_ID"] = $k;
                break;
            }
            
            
            
        }
        
        $_SESSION["CONVEAD_PRODUCT_ID"] = $arResult["PRODUCT_ID"];
        $_SESSION["CONVEAD_PRODUCT_NAME"] = $arProduct["NAME"];
        $_SESSION["CONVEAD_PRODUCT_URL"] = "http://" . SITE_SERVER_NAME . $arProduct["DETAIL_PAGE_URL"];

        $product_id = $arResult["PRODUCT_ID"];
        $product_name = $arProduct["NAME"];
        $product_url = "http://" . SITE_SERVER_NAME . $arProduct["DETAIL_PAGE_URL"];
        
        $result = $tracker->eventProductView($product_id, $product_name, $product_url, $APPLICATION->GetCurPage());

        return true;
    }
    
    static function login($arResult) {
        
        $arResult = $arResult["user_fields"];
        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
            return;

        global $APPLICATION;
        
        if(!$arResult["ID"])
            return;
        
        $visitor_uid = $arResult["ID"];
        
        $visitor_info = false;
        if ($visitor_uid && $visitor_info = self::getVisitorInfo($visitor_uid)) {
            
        }
        $guest_uid = self::getUid(false);
        $tracker = new ConveadTracker($api_key, $guest_uid, $visitor_uid, $visitor_info, false, SITE_SERVER_NAME);

        
        $url = "http://" . SITE_SERVER_NAME . "/login";
        $title = "Вход";
        
        $result = $tracker->view($url, $title);

        return true;
    }

    static function addToCart($arFields) {
        return true;
        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
            return;
        
        $visitor_uid = false;
        $visitor_info = false;
        if ($arFields["FUSER_ID"] && $arFields["FUSER_ID"] && $visitor_info = self::getVisitorInfo($arFields["FUSER_ID"])) {
            $visitor_uid = $arFields["FUSER_ID"];
        }
        $guest_uid = self::getUid($visitor_uid);

        $tracker = new ConveadTracker($api_key, $guest_uid, $visitor_uid, $visitor_info, false, SITE_SERVER_NAME);

        $product_id = $arFields["PRODUCT_ID"];
        $qnt = $arFields["QUANTITY"];
        $product_name = $arFields["NAME"];
        $product_url = "http://" . SITE_SERVER_NAME . $arFields["DETAIL_PAGE_URL"];
        $price = $arFields["PRICE"];

        $result = $tracker->eventAddToCart($product_id, $qnt, $product_name, $product_url, $price);

        return true;
    }

    static function updateCart($id, $arFields = false) {
        
        if(!class_exists("CCatalogSku"))
            return false;
        
        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
            return;
        
        if ($arFields && !isset($arFields["PRODUCT_ID"]) && !isset($arFields["DELAY"])) {//just viewving
            return;
        }
        
        if ($arFields && isset($arFields["ORDER_ID"])) {// purchasing
            return;
        }



        $basket = CSaleBasket::GetByID($id);
        
        $user_id = $basket["FUSER_ID"];
        $items = array();
        $orders = CSaleBasket::GetList(
                        array(), array(
                    "FUSER_ID" => $basket["FUSER_ID"],
                    //"LID" => SITE_ID,
                    "ORDER_ID" => "NULL"
                        ), false, false, array()
        );
        $i = 0;
        while ($order = $orders->Fetch()) {
            if (!$arFields) {//deleting
                if ($order["ID"] == $id)
                    continue;
            }

            $item["product_id"] = $order["PRODUCT_ID"];
            
            $item["qnt"] = $order["QUANTITY"];
            $item["price"] = $order["PRICE"];
            $items[$i . ""] = $item;
            $i++;
        }

        //if(count($items) == 0)
        //    return;

        global $USER;
        $user_id = false;
        if($USER->GetID())
            $user_id = $USER->GetID();
        
        $visitor_uid = false;
        $visitor_info = false;
        $visitor_info = self::getVisitorInfo($user_id);
        if ($visitor_info || $user_id !== FALSE) {
            $visitor_uid = $user_id;
        }
        $guest_uid = self::getUid($visitor_uid);
        
        $tracker = new ConveadTracker($api_key, $guest_uid, $visitor_uid, $visitor_info, false, SITE_SERVER_NAME);
        
        $result = $tracker->eventUpdateCart($items);

        return true;
    }

    static function removeFromCart($id) {
        return true;
        $arFields = CSaleBasket::GetByID($id);

        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
            return;
        
        $visitor_uid = false;
        $visitor_info = false;
        if ($arFields["FUSER_ID"] && $arFields["FUSER_ID"] && $visitor_info = self::getVisitorInfo($arFields["FUSER_ID"])) {
            $visitor_uid = $arFields["FUSER_ID"];
        }
        $guest_uid = self::getUid($visitor_uid);
        $tracker = new ConveadTracker($api_key, $guest_uid, $visitor_uid, $visitor_info, false, SITE_SERVER_NAME);

        $product_id = $arFields["PRODUCT_ID"];
        $qnt = $arFields["QUANTITY"];
        $product_name = $arFields["NAME"];
        $product_url = "http://" . SITE_SERVER_NAME . $arFields["DETAIL_PAGE_URL"];
        $price = $arFields["PRICE"];

        $result = $tracker->eventRemoveFromCart($product_id, $qnt);


        return true;
    }

    static function order($ID, $fuserID, $strLang, $arDiscounts)
      {
        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
          return;
        $arOrder = CSaleOrder::GetByID(intval($ID));
        if ($arOrder["ID"] > 0)
          {

            $TimeUpdate = strtotime($arOrder["DATE_UPDATE"]);
            $TimeAdd = strtotime($arOrder["DATE_INSERT"]);
            if ($TimeUpdate - $TimeAdd <= 60)
              {
                $visitor_uid = false;
                $visitor_info = false;
                if ($arOrder["USER_ID"] && $arOrder["USER_ID"] &&
                   $visitor_info = self::getVisitorInfo($arOrder["USER_ID"])
                )
                  {
                    $visitor_uid = $arOrder["USER_ID"];
                  }
                $guest_uid = self::getUid($visitor_uid);
                $tracker =
                   new ConveadTracker($api_key, $guest_uid, $visitor_uid, $visitor_info, false, SITE_SERVER_NAME);

                $items = array();
                $orders = CSaleBasket::GetList(array(), array(
                   "ORDER_ID" => $arOrder["ID"]
                ), false, false, array());
                $i = 0;
                while ($order = $orders->Fetch())
                  {
                    $arProd = CCatalogSku::GetProductInfo($order["PRODUCT_ID"]);
                    $item["product_id"] = isset($arProd["ID"]) ? $arProd["ID"] : $order["PRODUCT_ID"];
                    $item["qnt"] = $order["QUANTITY"];
                    $item["price"] = $order["PRICE"];
                    $items[$i . ""] = $item;
                    $i++;
                  }
                if (!empty($items))
                  {
                    $price = $arOrder["PRICE"] - (isset($arOrder["PRICE_DELIVERY"]) ? $arOrder["PRICE_DELIVERY"] : 0);
                    $result = $tracker->eventOrder($ID, $price, $items);
                  }
              }
          }
        return true;
      }

    static function view() {
        return true;
        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
            return;

        global $USER;
        global $APPLICATION;




        
        $visitor_info = false;
        $visitor_uid = false;
        if ($USER->GetID() && $USER->GetID() > 0 && $visitor_info = self::getVisitorInfo($USER->GetID())) {
            $visitor_uid = $USER->GetID();
        }
        $guest_uid = self::getUid($visitor_uid);
        $title = $APPLICATION->GetTitle();
        $url = $APPLICATION->GetCurUri();
        if (self::endsWith($url, "ajax.php?UPDATE_STATE")) {
            return;
        } elseif (self::startsWith($url, "/bitrix/admin/")) {
            return;
        } elseif (self::contains($url, "/bitrix/tools/captcha.php")) {
            return;
        } elseif (self::contains($url, "bitrix/tools/autosave.php?bxsender=core_autosave")) {
            return;
        }


        $tracker = new ConveadTracker($api_key, $guest_uid, $visitor_uid, $visitor_info, false, SITE_SERVER_NAME);

        $result = $tracker->view($url, $title);



        return true;
    }

    static function HeadScript($api_key)
      {
        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
            return;

        global $USER;
        global $APPLICATION;

        $url = $APPLICATION->GetCurUri();
        if (self::startsWith($url, "/bitrix/admin/")) {
            return;
        }

        
        $visitor_info = false;
        $visitor_uid = false;
        if ($USER && $USER->GetID() && $USER->GetID() > 0 && $visitor_info = self::getVisitorInfo($USER->GetID())) {
            $visitor_uid = $USER->GetID();
        }
        $guest_uid = self::getUid($visitor_uid);
        $vi = "";
        if ($visitor_info) {
            foreach ($visitor_info as $key => $val) {
                $vi.="\n" . $key . ": '" . $val . "',";
            }

            $vi = substr($vi, 1, strlen($vi) - 2);
        }

        $head = "<!-- Convead Widget -->
                    <script>
                    window.ConveadSettings = {
                        /* Use only [0-9a-z-] characters for visitor uid!*/
                        " . ($visitor_uid ? "visitor_uid: '$visitor_uid'," : "") . "
                        visitor_info: {
                            $vi
                        }, 
                        app_key: \"$api_key\"

                        /* For more information on widget configuration please see:
                           http://convead.uservoice.com/knowledgebase/articles/344831-how-to-embed-a-tracking-code-into-your-websites
                        */
                    };
                    
                    (function(w,d,c){w[c]=w[c]||function(){(w[c].q=w[c].q||[]).push(arguments)};var ts = (+new Date()/86400000|0)*86400;var s = d.createElement('script');s.type = 'text/javascript';s.async = true;s.src = 'http://tracker.convead.io/widgets/'+ts+'/widget-$api_key.js';var x = d.getElementsByTagName('script')[0];x.parentNode.insertBefore(s, x);})(window,document,'convead');
                    </script>
                    <!-- /Convead Widget -->";
        if(isset($_SESSION["CONVEAD_PRODUCT_ID"])){
            
            $head1 = "<!-- Convead view product -->
                    <script>
                    var callback = function(event) { 
                      convead('event', 'view_product', {
                            product_id: ".$_SESSION["CONVEAD_PRODUCT_ID"].",
                            product_name: '".$_SESSION["CONVEAD_PRODUCT_NAME"]."',
                            product_url: '".$_SESSION["CONVEAD_PRODUCT_URL"]."'
                          });
                        
                    };
                    if (window.attachEvent) document.attachEvent(\"onreadystatechange\", function(event) { callback(event); });
                        else document.addEventListener(\"DOMContentLoaded\", function(event) { callback(event); });

                    
                    </script>
                    <!-- /Convead view product -->";
            //echo $head1;

            $_SESSION["CONVEAD_PRODUCT_ID"] = null;
            $_SESSION["CONVEAD_PRODUCT_NAME"] = null;
            $_SESSION["CONVEAD_PRODUCT_URL"] = null;
            unset($_SESSION["CONVEAD_PRODUCT_ID"]);
            unset($_SESSION["CONVEAD_PRODUCT_NAME"]);
            unset($_SESSION["CONVEAD_PRODUCT_URL"]);
        }
        return $head.$head1;
      }

    static function head()
      {
        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
          return;
        if (CHTMLPagesCache::IsOn())
          {
            $frame = new \Bitrix\Main\Page\FrameHelper("platina_conveadtracker");
            $frame->begin();
            $actionType = \Bitrix\Main\Context::getCurrent()->getServer()->get("HTTP_BX_ACTION_TYPE");
            if (true/*$actionType == "get_dynamic"*/)
              {
                echo self::HeadScript($api_key);
              }
            $frame->beginStub();
            $frame->end();
          }
        else
          {
            global $APPLICATION;
            $APPLICATION->AddHeadString(self::HeadScript($api_key), false, true);
          }
        return true;
      }


    static function productViewCustom($id, $arFields) {
        if($arFields["PRODUCT_ID"])
            $arResult["PRODUCT_ID"] = $arFields["PRODUCT_ID"];
        else if($id["PRODUCT_ID"])
            $arResult["PRODUCT_ID"] = $id["PRODUCT_ID"];
        else
            return true;

        if (self::contains($_SERVER["HTTP_USER_AGENT"], "facebook.com")) {
            return;
        }


        $api_key = COption::GetOptionString(self::$MODULE_ID, "tracker_code", '');
        if (!$api_key)
            return;

        global $APPLICATION;
        global $USER;
        
        $visitor_uid = false;
        if(!$user_id)
            $user_id = $USER->GetID();

        $visitor_info = false;
        if ($user_id && $visitor_info = self::getVisitorInfo($user_id)) {
            $visitor_uid = (int) $user_id;
        }
        $guest_uid = self::getUid($visitor_uid);
        $tracker = new ConveadTracker($api_key, $guest_uid, $visitor_uid, $visitor_info, false, SITE_SERVER_NAME);

        $arProduct = CCatalogProduct::GetByIDEx($arResult["PRODUCT_ID"]);

        $product_id = $arResult["PRODUCT_ID"];
        $product_name = $arProduct["NAME"];
        $product_url = "http://" . SITE_SERVER_NAME . $arProduct["DETAIL_PAGE_URL"];
        
        $result = $tracker->eventProductView($product_id, $product_name, $product_url, $APPLICATION->GetCurPage());

        return true;
    }

    private static function startsWith($haystack, $needle) {
        return $needle === "" || strpos($haystack, $needle) === 0;
    }

    private static function endsWith($haystack, $needle) {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

    private static function contains($haystack, $needle) {
        return $needle === "" || strpos($haystack, $needle) !== false;
    }

}
