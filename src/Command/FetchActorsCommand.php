<?php

namespace App\Command;

use \Exception;
use OaiPmhApiUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FetchActorsCommand extends Command
{
    private $params;
    private $metadataPrefix;
    private $namespace;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("app:fetch-actors");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $oaiPmhApi = $this->params->get('oai_pmh_api');
        $this->metadataPrefix = $oaiPmhApi['metadata_prefix'];
        $this->namespace = $oaiPmhApi['namespace'];
        $filename = $this->params->get('filename');
        $xpaths = $this->params->get('xpaths');

        $overrideCaCert = $this->params->get('override_ca_cert');
        $caCert = $this->params->get('ca_cert');

        $actors = [];

        try {
            if(array_key_exists('username', $oaiPmhApi) && array_key_exists('password', $oaiPmhApi)) {
                $oaiPmhEndpoint = OaiPmhApiUtil::connect($oaiPmhApi['url'], $overrideCaCert, $caCert, $oaiPmhApi['username'], $oaiPmhApi['password']);
            } else {
                $oaiPmhEndpoint = OaiPmhApiUtil::connect($oaiPmhApi['url'], $overrideCaCert, $caCert);
            }
            if(array_key_exists('set', $oaiPmhApi)) {
                $records = $oaiPmhEndpoint->listRecords($this->metadataPrefix, null, null, $oaiPmhApi['set']);
            } else {
                $records = $oaiPmhEndpoint->listRecords($this->metadataPrefix);
            }

            $objectIdXpath = $this->buildXPath($xpaths['object_id'], $this->namespace);
            $actorXPath = $this->buildXPath($xpaths['actor'], $this->namespace);
            $nameXpath = $this->buildXPath($xpaths['name'], $this->namespace);
            $alternativeNamesXpath = $this->buildXPath($xpaths['alternative_names'], $this->namespace);
            $externalAuthoritiesXpath = $this->buildXPath($xpaths['external_authorities'], $this->namespace);
            $roleNlXpath = $this->buildXPath($xpaths['role_nl'], $this->namespace);
            $roleEnXpath = $this->buildXPath($xpaths['role_en'], $this->namespace);

            foreach($records as $record) {
                $data = $record->metadata->children($this->namespace, true);
                $actorsRes = $data->xpath($actorXPath);
                if ($actorsRes) {
                    if (count($actorsRes) > 0) {
                        $objectId = null;
                        $objectIds = $data->xpath($objectIdXpath);
                        if ($objectIds) {
                            foreach ($objectIds as $id) {
                                $objectId = (string)$id;
                            }
                        }
                        if ($objectId !== null) {
                            foreach ($actorsRes as $actor) {
                                //Get the name of the actor
                                $name = null;
                                $actorNames = $actor->xpath($nameXpath);
                                if ($actorNames) {
                                    foreach ($actorNames as $actorName) {
                                        $name = (string)$actorName;
                                        break;
                                    }
                                }
                                if ($name !== null) {
                                    if (!array_key_exists($name, $actors)) {
                                        $actors[$name] = [];
                                    }

                                    $actorAltNames = $actor->xpath($alternativeNamesXpath);
                                    if ($actorAltNames) {
                                        foreach ($actorAltNames as $altName_) {
                                            $altName = (string)$altName_;
                                            if($altName !== $name) {
                                                if(!array_key_exists('alternative_names', $actors[$name])) {
                                                    $actors[$name]['alternative_names'] = [];
                                                }
                                                if(!in_array($altName, $actors[$name]['alternative_names'])) {
                                                    $actors[$name]['alternative_names'][] = $altName;
                                                }
                                            }
                                        }
                                    }

                                    $actorAuthorityIds = $actor->xpath($externalAuthoritiesXpath);
                                    if ($actorAuthorityIds) {
                                        foreach ($actorAuthorityIds as $id_) {
                                            $id = (string)$id_;
                                            if(!array_key_exists('external_authorities', $actors[$name])) {
                                                $actors[$name]['external_authorities'] = [];
                                            }
                                            if(!in_array($id, $actors[$name]['external_authorities'])) {
                                                $actors[$name]['external_authorities'][] = $id;
                                            }
                                        }
                                    }

                                    //Get the role of the actor related to this work
                                    $roleNl = null;
                                    $roleEn = null;
                                    $rolesNl = $actor->xpath($roleNlXpath);
                                    $rolesEn = $actor->xpath($roleEnXpath);
                                    if ($rolesNl) {
                                        foreach ($rolesNl as $role) {
                                            $roleNl = (string)$role;
                                        }
                                    }
                                    if ($rolesEn) {
                                        foreach ($rolesEn as $role) {
                                            $roleEn = (string)$role;
                                        }
                                    }
                                    if(!array_key_exists('works', $actors[$name])) {
                                        $actors[$name]['works'] = [];
                                    }
                                    if($roleNl !== null) {
                                        if($roleEn !== null) {
                                            $actors[$name]['works'][] = [
                                                'id' => $objectId,
                                                'role_nl' => $roleNl,
                                                'role_en' => $roleEn
                                            ];
                                        } else {
                                            $actors[$name]['works'][] = [
                                                'id' => $objectId,
                                                'role_nl' => $roleNl
                                            ];
                                        }
                                    } else if($roleEn !== null) {
                                        $actors[$name]['works'][] = [
                                            'id' => $objectId,
                                            'role_en' => $roleEn
                                        ];
                                    } else {
                                        $actors[$name]['works'][] = [
                                            'id' => $objectId
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch(Exception $e) {
            echo $e . PHP_EOL;
        }

        if(!empty($actors)) {
            ksort($actors);
            $fp = fopen($filename, 'w');
            fwrite($fp, json_encode($actors, JSON_PRETTY_PRINT));
            fclose($fp);
        }

        return 0;
    }

    // Builds an xpath-expression based on the provided namespace (there are probably cleaner solutions)
    private function buildXPath($xpath, $namespace)
    {
        $prepend = '';
        if(strpos($xpath, '(') === 0) {
            $prepend = '(';
            $xpath = substr($xpath, 1);
        }
        $xpath = preg_replace('/\[@(?!xml|text)/', '[@' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\(@(?!xml|text)/', '(@' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\[(?![@0-9]|not\(|text|position\()/', '[' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\/([^\/])/', '/' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/ and @(?!xml)/', ' and @' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/ and not\(([^@])/', ' and not(' . $namespace . ':${1}', $xpath);
        if(strpos($xpath, '/') !== 0) {
            $xpath = $namespace . ':' . $xpath;
        }
        $xpath = 'descendant::' . $xpath;
        $xpath = $prepend . $xpath;
//        echo $xpath . PHP_EOL;
        return $xpath;
    }
}
