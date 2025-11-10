<?php

##### Custom Classes Imports #####
use PleskExt\Utils\Validation\InputSanitizer;
use PleskExt\Desec\Domains;
use PleskExt\Desec\Account;
use PleskExt\Utils\DomainUtils;
use PleskExt\Utils\Settings;
use PleskExt\Utils\MyLogger;
##### Plesk Classes Imports #####

class ApiController extends pm_Controller_Action
{

    protected $_accessLevel = 'admin';

    private DomainUtils $domainUtils;
    private Domains $desecDomains;
    private MyLogger $myLogger;

    /**
     * @throws pm_Exception
     */
    public function init() {
        parent::init();

        if (!pm_Session::getClient()->isAdmin()) {
            throw new pm_Exception('Permission denied');
        }

        $this->domainUtils = new DomainUtils();
        $this->desecDomains = new Domains();
        $this->myLogger = new MyLogger();
    }

    public function getDomainsInfoAction(): void
    {

        try {
            if ($this->getRequest()->isGet()) {
                $domainInfo = $this->domainUtils->getPleskDomains($this->view);
                $this->myLogger->log("info", "Successfully retrieved the informations regarding the domains!");
                $this->_helper->json($domainInfo);
            }

        } catch (Exception $e) {

            $this->myLogger->log("error", $e->getMessage());
            $failureResponse = ["error" =>
                ["message" => $e->getMessage()]
            ];


            $this->_helper->json($failureResponse);
        }
    }


    // ################ Domain Retention Methods ################

    public function saveDomainRetentionStatusAction(): void
    {

        try {
            if ($this->getRequest()->isPost()) {

                $data = InputSanitizer::readJsonBody();
                $status = InputSanitizer::normalizeBool($data[0]);

                pm_Settings::set(Settings::DOMAIN_RETENTION->value, $status);
                $this->myLogger->log("info", "Successfully saved the domain retention status! Current Status: " . $status);

            }
        } catch (Exception $e) {
            $this->myLogger->log("error", "Failed to save domain retention setting. Error: " . $e->getMessage());

            $failureResponse = [ "error" =>
                [ "message" =>  "Failed to save domain retention setting. Error: " . $e->getMessage() ]
            ];

            $this->_helper->json($failureResponse);
        }

        $this->_helper->json(['success' => true]);
    }


    public function getDomainRetentionStatusAction(): void {

        try {
            if ($this->getRequest()->isGet()) {

                $domainRetentionStatus = pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false");
                $this->_helper->json(['success' => true, 'domain-retention' => $domainRetentionStatus]);
                $this->myLogger->log("info", "Successfully retrieved the domain retention status! Current Status: " . $domainRetentionStatus);
            }

        } catch (Exception $e) {
            $this->myLogger->log("error", "Failed to retrieve domain retention setting. Error: " . $e->getMessage());

            $this->_helper->json([
                'success' => false,
                'error'   => 'Failed to retrieve domain retention setting.'
            ]);
        }
    }

    // ################ Last-Sync & Auto-Sync Methods ################

    public function saveAutoSyncStatusAction()
    {

        try {
            if ($this->getRequest()->isPost()) {
                $data = InputSanitizer::readJsonBody();

                if ($data === [] || array_values($data) === $data) {
                    throw new Exception("Invalid data format!");
                }

                foreach ($data as $id => $statusRaw) {
                    $domainId = InputSanitizer::validateDomainId($id);
                    $status = InputSanitizer::normalizeBool($statusRaw);

                    $domain_obj = pm_Domain::getByDomainId($domainId);
                    $domain_obj->setSetting(Settings::AUTO_SYNC_STATUS->value, $status);
                    $this->myLogger->log("info", "Successfully saved the domain's auto-sync status for  " . $domain_obj->getName() . ". Current Status: " . $status);
                }
            }
        } catch (Exception $e) {
            $this->myLogger->log("info", "Failed to save the auto-sync status! Error: " . $e->getMessage());

            $this->_helper->json([
                'success' => false,
                'error'   => 'Failed to save domain(s) auto-sync status setting.'
            ]);
        }

        $this->_helper->json(['success' => true]);
    }


    // ################ deSEC Methods ################

    /**
     * @throws Zend_Controller_Response_Exception
     * @throws Exception
     */
    public function registerDomainAction() {

        if ($this->getRequest()->isPost()) {
            $result_desec = [];

            $payload = InputSanitizer::readJsonBody(); // list of IDs
            if ($payload === [] || array_values($payload) !== $payload) {
                throw new Exception("Invalid data format!");
            }

            $ids = array_unique(array_map(fn($id) => InputSanitizer::validateDomainId($id), $payload));

            foreach ($ids as $domain_id) {
                try {
                    $domain_obj = pm_Domain::getByDomainId($domain_id);
                    $domain = $domain_obj->getName();
                    $result_desec[$domain] = $this->desecDomains->addDomain($domain);


                } catch (Exception  $e) {
                    $this->myLogger->log("error", "Error occurred during domain registration with deSEC: " . $e->getMessage());

                    pm_Domain::getByName($domain)->setSetting(Settings::DESEC_STATUS->value, "Error");
                    $this->getResponse()->setHttpResponseCode(500);

                    $failureResponse = [ "error" =>
                        [ "message" =>  $e->getMessage(), "failed_domain" =>  pm_Domain::getByDomainId($domain_id)->getName() ]
                    ];

                    if(!empty($result_desec)) {
                        $failureResponse["error"]["results"] = $result_desec;
                    }

                    $this->_helper->json($failureResponse);
                }
            }

            $this->myLogger->log("info", "Successfully registered domains in deSEC:\n" . print_r($payload, true));
            $this->_helper->json($result_desec);
        }
    }

//    public function syncDnsZoneAction()
//    {
//        if ($this->getRequest()->isPost()) {
//            $payload = InputSanitizer::readJsonBody();
//
//            if ($payload === [] || array_values($payload) !== $payload) {
//                throw new Exception("Invalid data format!");
//            }
//
//            $ids = array_unique(array_map(fn($id) => InputSanitizer::validateDomainId($id), $payload));
//
//            $this->myLogger->log("debug","Ids: " . json_encode($ids));
//            $summary = array();
//
//            foreach ($ids as $domain_id) {
//                try {
//                    $summary[$domain_id] = $this->domainUtils->syncDomain($domain_id);
//
//                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "SUCCESS");
//                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, $summary[$domain_id]['timestamp']);
//
//                } catch(Exception $e) {
//                    $this->myLogger->log("error","Error occurred during DNS synchronization with deSEC: " . $e->getMessage());
//
//
//                    $timestamp = new DateTime()->format('Y-m-d H:i:s T');
//
//                    $failureResponse = [ "error" =>
//                        [ "message" =>  $e->getMessage(), "domainId" => $domain_id, "timestamp" => $timestamp ]
//                    ];
//
//                    if(!empty($summary)) {
//                        $failureResponse["error"]["results"] = $summary;
//                    }
//
//                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "FAILED");
//                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, $timestamp);
//
//                    $this->_helper->json($failureResponse);
//                }
//            }
//
//            $this->myLogger->log("info", "Successfully synced the DNS zone of the domains:\n" . print_r($payload, true));
//            $this->_helper->json($summary);
//
//        }
//    }

    public function syncDnsZoneAction()
    {
        if (!$this->getRequest()->isPost()) {
            throw new Exception('POST required');
        }

        $payload = InputSanitizer::readJsonBody();

        if ($payload === [] || array_values($payload) !== $payload) {
            throw new Exception('Invalid data format!');
        }

        $ids = array_unique(array_map(
            fn($id) => InputSanitizer::validateDomainId($id),
            $payload
        ));

        $this->myLogger->log('debug', 'Ids: ' . json_encode($ids));

        $task = new Modules_LsDesecDns_Task_SyncDnsZones();
        $task->setParam('ids', array_values($ids));

        // Optional: run without domain context; or use a specific pm_Domain if you prefer
        $manager = new pm_LongTask_Manager();
        $manager->start($task);

        $uid = $task->getInstanceId(); // unique per started task

        $this->myLogger->log('info', 'Started DNS sync long task uid=' . $uid);

        // Return a tiny descriptor that clients can poll
        $this->_helper->json([
            'taskUid'  => $uid,
            'statusUrl'=> pm_Context::getBaseUrl() . 'api/task-status?uid=' . urlencode($uid),
            'message'  => 'DNS sync started',
        ]);
    }

    public function retrieveTokenAction() {
        if ($this->getRequest()->isGet()) {
            try {
                if (pm_Settings::get(Settings::DESEC_TOKEN->value, "") ||
                    pm_Config::get("DESEC_TOKEN")) {
                    $this->myLogger->log("info", "deSEC API token was successfully retrieved!");
                    $this->_helper->json(["token" => "true"]);
                }

                $this->myLogger->log("info", "deSEC API token doesn't exist!");
                $this->_helper->json(["token" => "false"]);

            } catch(Exception $e) {
                $this->myLogger->log("error", "Error occurred while retrieving the API token! Error:" . $e->getMessage());


                $failureResponse = [ "error" =>
                    [ "message" =>  $e->getMessage() ]
                ];

                $this->_helper->json($failureResponse);
            }
        }
    }

    public function validateTokenAction() {
        if ($this->getRequest()->isPost()) {
            try {
                $payload = InputSanitizer::readJsonBody();
                $tokenValidity = new Account()->validateToken($payload[0]);

                if($tokenValidity["token"] === "true") {
                    pm_Settings::set(Settings::DESEC_TOKEN->value, $payload[0]);
                }

                $this->_helper->json($tokenValidity);

            } catch(Exception $e) {
                $this->myLogger->log("error", "Error occurred while validating the API token! Error: " . $e->getMessage());

                $failureResponse = [ "error" =>
                    [ "message" =>  $e->getMessage() ]
                ];

                $this->_helper->json($failureResponse);
            }
        }
    }

}
