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
                $quote = $this->getQuote();
                $quoteCustomerName = null;

                if (Mage::app() && Mage::app()->getRequest()) {
                    $params = Mage::app()->getRequest()->getParams();
                }

                $token = Mage::getStoreConfig("newsletter/mailchimp/token");
                $list = Mage::getStoreConfig("newsletter/mailchimp/list_id");
                
                $nameParam = Mage::getStoreConfig("newsletter/mailchimp/name_param");
                $nameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/name_merge_var");
                $lastNameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/last_name_merge_var");
                $genderNameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/gender_merge_var");

                $groupNameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/group_name_merge_var");
                $birthdayMergeVar = Mage::getStoreConfig("newsletter/mailchimp/birthday_merge_var");

                $originParam = Mage::getStoreConfig("newsletter/mailchimp/origin_param");
                $originMergeVar = Mage::getStoreConfig("newsletter/mailchimp/origin_merge_var");

                $groupIdParam = Mage::getStoreConfig("newsletter/mailchimp/group_id_param");
                $interestGroupParam = Mage::getStoreConfig("newsletter/mailchimp/interest_group_param");

                $defaultGroupId = Mage::getStoreConfig("newsletter/mailchimp/default_group_id");
                $defaultInterestGroup = Mage::getStoreConfig("newsletter/mailchimp/default_interest_group");

                if(isset($params["firstname"]) && (strlen($params["firstname"]) > 1) ){
                    $params[$nameParam] = $params["firstname"];
                    if(isset($params["lastname"]) && (strlen($params["lastname"]) > 1) ){
                        $params[$nameParam] .= " " . $params["lastname"];
                    }
                } else if ($quote != null) {
                    $quoteCustomerName = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();
                    if(strlen($quoteCustomerName) > 2){
                        $params[$nameParam] = $quoteCustomerName;
                    }
                }

                // if ($quote != null) {
                //     $quoteCustomerName = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();
                //     $params[$nameParam] = $quoteCustomerName;
                // }

                $email  = $subscriber->getEmail();
                $name   = isset($params[$nameParam]) ? ( !empty($lastNameMergeVar) ? $this->getName($params[$nameParam]) : $params[$nameParam] ) : "";
                $origin = isset($params[$originParam]) ? $params[$originParam] : "";
                
                $groupId = isset($params[$groupIdParam]) ? $params[$groupIdParam] : $defaultGroupId;
                $interestGroup = isset($params[$interestGroupParam]) ? $params[$interestGroupParam] : $defaultInterestGroup;
                
                $mailchimp = new MailChimp($token);
                $mailchimp->set_timeout(3);

                if (!empty($nameMergeVar) && !empty($name)){
                    if ( is_array($name) ) {
                        $mergeVars[$nameMergeVar]     = $name['firstName'];
                        $mergeVars[$lastNameMergeVar] = $name['lastName'];
                    } else {
                        $mergeVars[$nameMergeVar] = $name;
                    }
                }

                // Manda o sexo(gender) do cliente
                if(!empty($genderNameMergeVar)) {
                    try {
                        $customer = Mage::getModel("customer/customer"); 
                        $customer->setWebsiteId(Mage::app()->getWebsite('admin')->getId()); 
                        $customer->loadByEmail($email);

                        if($customer->getId()) {
                            $gender = $customer->getResource()->getAttribute('gender')->getSource()->getOptionText($customer->getData('gender'));
                            $gender = strtolower($gender);
                            
                            if($gender == "masculino" || $gender == "male" || $gender == "homem") {
                                $gender = "M";
                            } elseif($gender == "feminino" || $gender == "female" || $gender == "mulher") {
                                $gender = "F";
                            }

                            if(!empty($gender)) {
                                $mergeVars[$genderNameMergeVar] = $gender;
                            }
                        }
                    } catch (Exception $e) {
                    }
                }

                // Envia o Nome do Grupo(Group Name Merge Var) do cliente
                if(!empty($groupNameMergeVar)) {
                    try {
                        $customer = Mage::getModel("customer/customer");
                        $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
                        $customer->loadByEmail($email);

                        if ($customer->getId()) {
                            $group = $customer->getGroupId();
                            $groupName = Mage::getModel('customer/group')->load($group)->getCustomerGroupCode();

                            if(!empty($groupName)) {
                                $mergeVars[$groupNameMergeVar] = $groupName;
                            }
                        }
                    } catch (Exception $e) {
                        Mage::log($e->getMessage(), null, 'mailchimp.log');
                    }
                }

                // Envia a data de nascimento (Birthday Merge Var) do cliente
                if(!empty($birthdayMergeVar)) {
                    try {
                        $customer = Mage::getModel("customer/customer");
                        $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
                        $customer->loadByEmail($email);

                        if ($customer->getId()) {
                            $birthday = $customer->getDob();

                            if(!empty($birthday)) {
                                $mergeVars[$birthdayMergeVar] = $birthday;
                            }
                        }
                    } catch (Exception $e) {
                        Mage::log($e->getMessage(), null, 'mailchimp.log');
                    }
                }

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

                        if (!empty($originMergeVar) && !empty($origin))
                            $mergeVars[$originMergeVar] = $origin;

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
            Mage::log($e, null, 'mailchimp.log');
        }
    }

    private function getName($fullName)
    {        
        if(!$fullName) return '';

        $name = explode(' ', $fullName);
        $lastName = '';

        for ($i=1; $i < count($name); $i++) { 
            $lastName .= $name[$i].' ';
        }

        return array( 'firstName' => $name[0], 'lastName' => $lastName );
    }

    private function getQuote() {
        try {
            $session = Mage::getSingleton('checkout/session');
            return $session->getQuote();
        } catch(Exception $ex) {
            return null;
        }
    }
}