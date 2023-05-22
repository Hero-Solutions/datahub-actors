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
            $attributionNlXpath = $this->buildXPath($xpaths['attribution_nl'], $this->namespace);
            $attributionEnXpath = $this->buildXPath($xpaths['attribution_en'], $this->namespace);
            $birthDateXpath = $this->buildXPath($xpaths['birth_date'], $this->namespace);
            $deathDateXpath = $this->buildXPath($xpaths['death_date'], $this->namespace);
            $imageXpath = $this->buildXPath($xpaths['image'], $this->namespace);

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
                            $image = null;
                            $images = $data->xpath($imageXpath);
                            if($images) {
                                foreach($images as $img) {
                                    $image = (string) $img;
                                }
                            }
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

                                    $birthDate = null;
                                    $birthDates = $actor->xpath($birthDateXpath);
                                    if ($birthDates) {
                                        foreach ($birthDates as $date) {
                                            $birthDate = (string)$date;
                                        }
                                    }

                                    $deathDate = null;
                                    $deathDates = $actor->xpath($deathDateXpath);
                                    if ($deathDates) {
                                        foreach ($deathDates as $date) {
                                            $deathDate = (string)$date;
                                        }
                                    }

                                    if($birthDate !== null && !empty($birthDate)) {
                                        if(!array_key_exists('birth_date', $actors[$name])) {
                                            $actors[$name]['birth_date'] = $birthDate;
                                        } else {
                                            if(strlen($birthDate) > strlen($actors[$name]['birth_date'])) {
                                                $actors[$name]['birth_date'] = $birthDate;
                                            }
                                        }
                                    }
                                    if($deathDate !== null && !empty($deathDate)) {
                                        if(!array_key_exists('death_date', $actors[$name])) {
                                            $actors[$name]['death_date'] = $deathDate;
                                        } else {
                                            if(strlen($deathDate) > strlen($actors[$name]['death_date'])) {
                                                $actors[$name]['death_date'] = $deathDate;
                                            }
                                        }
                                    }

                                    $actorAuthorityIds = $actor->xpath($externalAuthoritiesXpath);
                                    if ($actorAuthorityIds) {
                                        foreach ($actorAuthorityIds as $id_) {
                                            $externalAuthoritySources = $id_->xpath('@' . $this->namespace . ':source');
                                            $externalAuthoritySource = null;
                                            if($externalAuthoritySources) {
                                                foreach($externalAuthoritySources as $source) {
                                                    $externalAuthoritySource = (string)$source;
                                                }
                                            }
                                            if($externalAuthoritySource !== null) {
                                                $id = (string)$id_;
                                                str_replace('http://', 'https://', $id);
                                                if (!array_key_exists('external_authorities', $actors[$name])) {
                                                    $actors[$name]['external_authorities'] = [];
                                                }
                                                if (!in_array($id, $actors[$name]['external_authorities'])) {
                                                    $actors[$name]['external_authorities'][$externalAuthoritySource] = $id;
                                                }
                                            }
                                        }
                                    }

                                    if(!array_key_exists('works', $actors[$name])) {
                                        $actors[$name]['works'] = [];
                                    }
                                    $work = [];

                                    if($image !== null) {
                                        $work['image'] = $image;
                                    }

                                    //Get the role of the actor related to this work
                                    $roleNl = null;
                                    $rolesNl = $actor->xpath($roleNlXpath);
                                    if ($rolesNl) {
                                        foreach ($rolesNl as $role) {
                                            $roleNl = (string)$role;
                                        }
                                    }
                                    if($roleNl !== null) {
                                        $work['role_nl'] = $roleNl;
                                    }

                                    $roleEn = null;
                                    $rolesEn = $actor->xpath($roleEnXpath);
                                    if ($rolesEn) {
                                        foreach ($rolesEn as $role) {
                                            $roleEn = (string)$role;
                                        }
                                    }
                                    if($roleEn !== null) {
                                        $work['role_en'] = $roleEn;
                                    }

                                    //Get the attribution of the actor related to this work
                                    $attributionNl = null;
                                    $attributionsNl = $actor->xpath($attributionNlXpath);
                                    if ($attributionsNl) {
                                        foreach ($attributionsNl as $attribution) {
                                            $attributionNl = (string)$attribution;
                                        }
                                    }
                                    if($attributionNl !== null) {
                                        $work['attribution_nl'] = $attributionNl;
                                    }

                                    $attributionEn = null;
                                    $attributionsEn = $actor->xpath($attributionEnXpath);
                                    if ($attributionsEn) {
                                        foreach ($attributionsEn as $attribution) {
                                            $attributionEn = (string)$attribution;
                                        }
                                    }
                                    if($attributionEn !== null) {
                                        $work['attribution_en'] = $attributionEn;
                                    }

                                    $actors[$name]['works'][$objectId] = $work;
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
            $mergedActors = [];
            foreach($actors as $name => $actor) {
                if(array_key_exists($name, $mergedActors)) {
                    continue;
                }

                $nameKey = $name;
                $actorValue = $actor;
                $rkdId = null;
                if(array_key_exists('external_authorities', $actor)) {
                    if(array_key_exists('RKD', $actor['external_authorities'])) {
                        $rkdId = $actor['external_authorities']['RKD'];
                    }
                }
                if($rkdId !== null) {
                    foreach($actors as $name1 => $actor1) {
                        if($name1 !== $name) {
                            $rkdId1 = null;
                            if (array_key_exists('external_authorities', $actor1)) {
                                if (array_key_exists('RKD', $actor1['external_authorities'])) {
                                    $rkdId1 = $actor1['external_authorities']['RKD'];
                                }
                            }
                            //Merge actors with the same RKD ID but different name
                            if ($rkdId1 !== null && $rkdId1 === $rkdId) {
                                $actorValue = array_merge($actor1, $actor);
                                if(!array_key_exists('alternative_names', $actorValue)) {
                                    $actorValue['alternative_names'] = [];
                                }
                                //Add the name of one entry as alternative name to the other entry
                                //Give preference to name1 if name contains ',', '(' or ')'
                                if(strpos($name, ',') !== false || strpos($name, '(') !== false || strpos($name, ')') !== false) {
                                    if(!in_array($name, $actorValue['alternative_names'])) {
                                        $actorValue['alternative_names'][] = $name;
                                    }
                                    $nameKey = $name1;
                                } else {
                                    if(!in_array($name1, $actorValue['alternative_names'])) {
                                        $actorValue['alternative_names'][] = $name1;
                                    }
                                }
                                $actorValue['alternative_names'] = array_unique($actorValue['alternative_names']);
                            }
                        }
                    }
                }
                $mergedActors[$nameKey] = $actorValue;
            }

            ksort($mergedActors);
            $fp = fopen($filename, 'w');
            fwrite($fp, json_encode($mergedActors, JSON_PRETTY_PRINT));
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
        $xpath = preg_replace('/\[@(?!xml|text|contains|last)/', '[@' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\(@(?!xml|text|contains|last)/', '(@' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\[(?![@0-9]|not\(|text|contains|last|position\()/', '[' . $namespace . ':${1}', $xpath);
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
