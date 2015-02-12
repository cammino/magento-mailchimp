<?php

require_once(Mage::getBaseDir('lib') . '/MailChimp/MailChimp.php');

class Cammino_Mailchimp_Model_Observer_Subscriber extends Varien_Object
{

    public function sync(Varien_Event_Observer $observer) {

        try {

            $active = Mage::getStoreConfig("newsletter/mailchimp/active");

            if (intval($active) == 1) {

                $event = $observer->getEvent();
                $subscriber = $event->getSubscriber();
                $params = array();
                $mergeVars = array();

                if (Mage::app() && Mage::app()->getRequest()) {
                    $params = Mage::app()->getRequest()->getParams();
                }

                $token = Mage::getStoreConfig("newsletter/mailchimp/token");
                $list = Mage::getStoreConfig("newsletter/mailchimp/list_id");
                $nameParam = Mage::getStoreConfig("newsletter/mailchimp/name_param");
                $nameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/name_merge_var");

                $groupIdParam = Mage::getStoreConfig("newsletter/mailchimp/group_id_param");
                $interestGroupParam = Mage::getStoreConfig("newsletter/mailchimp/interest_group_param");

                $defaultGroupId = Mage::getStoreConfig("newsletter/mailchimp/default_group_id");
                $defaultInterestGroup = Mage::getStoreConfig("newsletter/mailchimp/default_interest_group");

                $email = $subscriber->getEmail();
                $name = isset($params[$nameParam]) ? $params[$nameParam] : "";
                
                $groupId = isset($params[$groupIdParam]) ? $params[$groupIdParam] : $defaultGroupId;
                $interestGroup = isset($params[$interestGroupParam]) ? $params[$interestGroupParam] : $defaultInterestGroup;
                
                $mailchimp = new MailChimp($token);
                $mailchimp->set_timeout(3);

                if (!empty($nameMergeVar) && !empty($name))
                    $mergeVars[$nameMergeVar] = $name;

                if (!empty($groupId))
                    $mergeVars["GROUPINGS"] = array(array( 'id' => $groupId ));

                if (!empty($interestGroup) && isset($mergeVars["GROUPINGS"]))
                    $mergeVars["GROUPINGS"][0]["groups"] = array($interestGroup);

                $request = array(
                    'id' => $list,
                    'email' => array( 'email'  => $email ),
                    'replace_interests' => 'false',
                    'merge_vars' => $mergeVars,
                );

                $callResult = $mailchimp->call('lists/update-member', $request);

                if (isset($callResult["status"])) {

                    if ((strval($callResult["name"]) == "Email_NotExists") || (strval($callResult["name"]) == "List_NotSubscribed")) {

                        $request = array(
                            'id' => $list,
                            'email' => array('email' => $email ),
                            'double_optin' => 'false',
                            'send_welcome' => 'false',
                            'replace_interests' => 'false',
                            'merge_vars' => $mergeVars
                        );

                        $callResult = $mailchimp->call('lists/subscribe', $request);
                    }
                }

            }

        } catch (Exception $e) {
        }
    }
}