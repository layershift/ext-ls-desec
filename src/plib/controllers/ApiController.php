<?php

require_once __DIR__ . '/../library/utils/Settings.php';
require_once __DIR__ . '/../library/desec/Account.php';
require_once __DIR__ . '/../library/DomainUtils.php';
require_once __DIR__ . "/../library/desec/Domains.php";
require_once __DIR__ . "/../library/desec/Dns.php";

use desec\Domains;
use library\desec\Account;
use library\utils\Settings;
use library\DomainUtils;
use Psr\Log\LoggerInterface;



class ApiController extends pm_Controller_Action
{
    private $logger;

    public function getLogger() {
        if (!$this->logger) {
            $logger = pm_Bootstrap::getContainer()->get(LoggerInterface::class);
        }

        return $logger;
    }
    public function getDomainsInfoAction(): void
    {

        try {
            $pleskDomains = new DomainUtils();
            if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                $this->getLogger()->debug("Successfully retrieved the informations regarding the domains!");
            }

            $this->_helper->json($pleskDomains->getPleskDomains());


        } catch (Exception $e) {
            if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                $this->getLogger()->error($e->getMessage());
            }
            $failureResponse = [ "error" =>
                [ "message" =>  $e->getMessage() ]
            ];

            $this->_helper->json($failureResponse);
        }
    }

    // ################ Domain Retention Methods ################

    public function saveDomainRetentionStatusAction(): void
    {

        if ($this->getRequest()->isPost()) {
            $responseArray = json_decode(file_get_contents('php://input'), true);

            try {
                pm_Settings::set(Settings::DOMAIN_RETENTION->value, $responseArray[0]);
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Successfully saved the domain retention status: " . json_encode($responseArray));
                }
            } catch (Exception $e) {
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->error("Failed to save domain retention setting. Error: " . $e->getMessage());
                }

                $failureResponse = [ "error" =>
                    [ "message" =>  "Failed to save domain retention setting. Error: " . $e->getMessage() ]
                ];

                $this->_helper->json($failureResponse);
            }

            $this->_helper->json(['success' => true]);
        }
    }

    public function getDomainRetentionStatusAction(): void {

        if ($this->getRequest()->isGet()) {
            try {
                $this->_helper->json(['success' => true, 'domain-retention' => pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false")]);
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Successfully retrieved the domain retention status!");
                }
            } catch (Exception $e) {
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->error("Failed to retrieve domain retention setting. Error: " . $e);
                }
                $this->_helper->json([
                    'success' => false,
                    'error'   => 'Failed to retrieve domain retention setting.'
                ]);
            }
        }
    }

    // ################ Last-Sync & Auto-Sync Methods ################

    public function saveAutoSyncStatusAction()
    {
        if ($this->getRequest()->isPost()) {
            $responseArray = json_decode(file_get_contents('php://input'), true);

            try {
                foreach($responseArray as $id => $status) {
                    $domain_obj = pm_Domain::getByDomainId($id);
                    $domain_obj->setSetting(Settings::AUTO_SYNC_STATUS->value, $status);
                }
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Successfully saved domain(s) auto-sync status setting.");
                }
            } catch (Exception $e) {
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->error("Failed to save domain(s) auto-sync status setting. Error: " . $e);
                }
                $this->_helper->json([
                    'success' => false,
                    'error'   => 'Failed to save domain(s) auto-sync status setting.'
                ]);
            }

            $this->_helper->json(['success' => true]);
        }
    }

    // ################ Log Verbosity Methods ################
    public function saveLogVerbosityStatusAction()
    {
        if ($this->getRequest()->isPost()) {
            $responseArray = json_decode(file_get_contents('php://input'), true);

            try {
                pm_Settings::set(Settings::LOG_VERBOSITY->value, $responseArray[0]);
                $this->getLogger()->debug("Successfully saved the log verbosity status: " . json_encode($responseArray));

            } catch (Exception $e) {
                $this->getLogger()->error("Failed to save log verbosity setting. Error: " . $e->getMessage());

                $failureResponse = [ "error" =>
                    [ "message" => "Failed to save log verbosity setting. Error: " . $e->getMessage() ]
                ];

                $this->_helper->json($failureResponse);
            }

            $this->_helper->json(['success' => true]);
        }
    }

    public function getLogVerbosityStatusAction()
    {
        if ($this->getRequest()->isGet()) {
            try {
                $this->_helper->json(['success' => true, 'log-verbosity' => pm_Settings::get(Settings::LOG_VERBOSITY->value, "true")]);
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Successfully retrieved the domain retention status!");
                }
            } catch (Exception $e) {
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->error("Failed to retrieve domain retention setting. Error: " . $e);
                }

                $failureResponse = [ "error" =>
                    [ "message" => "Failed to save log verbosity setting. Error: " . $e->getMessage() ]
                ];

                $this->_helper->json($failureResponse);
            }
        }
    }

    // ################ deSEC Methods ################

    /**
     * @throws Zend_Controller_Response_Exception
     */
    public function registerDomainAction() {
        if ($this->getRequest()->isPost()) {

            $desec = new Domains();
            $result_desec = [];
            $i = 0;

            $payload = json_decode(file_get_contents('php://input'), true);

            foreach ($payload as $domain_id) {
                try {
                    $domain_obj = pm_Domain::getByDomainId($domain_id);
                    $domain = $domain_obj->getName();
                    $result_desec[$domain] = $desec->addDomain($domain);


                } catch (Exception  $e) {
                    if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                        $this->getLogger()->error("Error occurred during domain registration with deSEC: " . $e->getMessage());
                    }

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

            if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                $this->getLogger()->debug("Successfully registered domains in deSEC:\n" . print_r($result_desec, true));
            }

            $this->_helper->json($result_desec);

        }
    }

    public function syncDnsZoneAction()
    {
        if ($this->getRequest()->isPost()) {

            $payload = json_decode(file_get_contents('php://input'), true);

            $summary = array();
            $utils = new DomainUtils();

            foreach ($payload as $domain_id) {
                try {
                    $summary[$domain_id] = $utils->syncDomain($domain_id);

                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "SUCCESS");
                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, $summary[$domain_id]['timestamp']);

                } catch(Exception $e) {
                    if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                        $this->getLogger()->error("Error occurred during DNS synchronization with deSEC: " . $e->getMessage());
                    }

                    $timestamp = (new DateTime())->format('Y-m-d H:i:s T');

                    $failureResponse = [ "error" =>
                        [ "message" =>  $e->getMessage(), "domainId" => $domain_id, "timestamp" => $timestamp ]
                    ];

                    if(!empty($summary)) {
                        $failureResponse["error"]["results"] = $summary;
                    }

                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "FAILED");
                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, $timestamp);

                    $this->_helper->json($failureResponse);
                }
            }

            if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                $this->getLogger()->debug("Successfully synced the DNS zones of the domain(s) in deSEC:\n" . json_encode($summary, true));
            }

            $this->_helper->json($summary);


        }

    }

    public function retrieveTokenAction() {
        if ($this->getRequest()->isGet()) {
            try {
                if (pm_Settings::get(Settings::DESEC_TOKEN->value, "") ||
                    pm_Config::get("DESEC_API_TOKEN")) {

                    $this->_helper->json(["token" => "true"]);
                }
                $this->_helper->json(["token" => "false"]);

            } catch(Exception $e) {
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->error($e->getMessage());
                }

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
                $payload = json_decode(file_get_contents('php://input'), true);
                $tokenValidity = (new Account())->validateToken($payload[0]);

                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Token validation response: " . json_encode($tokenValidity));
                }

                if($tokenValidity["token"] === "true") {
                    pm_Settings::set(Settings::DESEC_TOKEN->value, $payload[0]);
                }

                $this->_helper->json($tokenValidity);

            } catch(Exception $e) {
                if(pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->error($e->getMessage());
                }

                $failureResponse = [ "error" =>
                    [ "message" =>  $e->getMessage() ]
                ];

                $this->_helper->json($failureResponse);
            }
        }
    }

}
