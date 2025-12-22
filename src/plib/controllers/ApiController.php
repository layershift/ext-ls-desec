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



    private DomainUtils $domainUtils;
    private Domains $desecDomains;
    private MyLogger $myLogger;

    /**
     * @throws pm_Exception
     */
    protected $_accessLevel = 'admin';
    public function init() {
        // Hard block before anything else runs
        if (!pm_Session::getClient() || !pm_Session::getClient()->isAdmin()) {
            exit;
        }

        parent::init();

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

            $payload = InputSanitizer::readJsonBody(); // list of IDs
            if ($payload === [] || array_values($payload) !== $payload) {
                throw new Exception("Invalid data format!");
            }

            $ids = array_unique(array_map(fn($id) => InputSanitizer::validateDomainId($id), $payload));
            $this->myLogger->log('debug', 'Ids: ' . json_encode($ids));

            $addDomainTask = new Modules_LsDesecDns_Task_RegisterDomains();
            $addDomainTask->setParam('ids', array_values($ids));
            $manager = new pm_LongTask_Manager();

            $tasks = $manager->getTasks([$addDomainTask->getId()]);

            foreach($tasks as $task) {
                $this->myLogger->log('info', 'Task uid: ' . $task->getInstanceId() . " status: " . $task->getStatus());

                if($task->getStatus() === "running") {
                    $this->_helper->json([
                        'error' => [
                            'message' => 'DNS sync already running.'
                        ],
                    ]);

                    return;
                }
            }

            $manager->start($addDomainTask);
            $uid = $addDomainTask->getInstanceId();


            $this->myLogger->log("info", "Successfully started to register domains in deSEC:\n" . print_r($payload, true));
            $this->_helper->json([
                'taskUid'  => $uid,
                'message'  => 'Register domains task started!',
            ]);
        }
    }

    /**
     * @throws pm_Exception
     * @throws Exception
     */
    public function syncDnsZoneAction()
    {
        if (!$this->getRequest()->isPost()) {
            throw new Exception('POST required');
        }

        $manager = new pm_LongTask_Manager();
        $payload = InputSanitizer::readJsonBody();

        if ($payload === [] || array_values($payload) !== $payload) {
            throw new Exception('Invalid data format!');
        }

        $ids = array_unique(array_map(
            fn($id) => InputSanitizer::validateDomainId($id),
            $payload
        ));

        $syncDomainTask = new Modules_LsDesecDns_Task_SyncDnsZones();
        $syncDomainTask->setParam('ids', array_values($ids));

        $this->myLogger->log('debug', 'Ids: ' . json_encode($ids));
        $this->myLogger->log('debug', 'Task count:' . count($manager->getTasks([$syncDomainTask->getId()])));

        $manager->start($syncDomainTask);

        $uid = $syncDomainTask->getInstanceId();
        $this->myLogger->log('info', 'Started DNS sync long task uid=' . $uid . ". Progress:" . $syncDomainTask->getStatus());

        $this->_helper->json([
            'taskUid'  => $uid,
            'message'  => 'DNS sync started',
        ]);
    }

    public function retrieveTokenAction(): void
    {
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
