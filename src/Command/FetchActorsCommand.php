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
            $nameWithCommaXpath = $this->buildXPath($xpaths['name_with_comma'], $this->namespace);
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

                                    $nameWithComma = null;
                                    $actorNamesWithComma = $actor->xpath($nameWithCommaXpath);
                                    if ($actorNamesWithComma) {
                                        foreach ($actorNamesWithComma as $actorNameWithComma) {
                                            $nameWithComma = (string)$actorNameWithComma;
                                            break;
                                        }
                                    }

                                    $nameLower = strtolower($name);
                                    if (!array_key_exists($nameLower, $actors)) {
                                        if($nameWithComma !== null) {
                                            $actors[$nameLower] = [
                                                'primary_name' => $name,
                                                'name_with_comma' => $nameWithComma,
                                                'alternative_names' => [
                                                    $name,
                                                    $nameLower,
                                                    $nameWithComma
                                                ]
                                            ];
                                        } else {
                                            echo 'Error: no name with comma found for actor ' . $name . ' (object ' . $objectId . ')' . PHP_EOL;
                                            $actors[$nameLower] = [
                                                'primary_name' => $name,
                                                'alternative_names' => [
                                                    $name,
                                                    $nameLower
                                                ]
                                            ];
                                        }
                                    }

                                    $actorAltNames = $actor->xpath($alternativeNamesXpath);
                                    if ($actorAltNames) {
                                        foreach ($actorAltNames as $altName_) {
                                            $altName = (string)$altName_;
                                            $lowerAltName = strtolower($altName);
                                            if($lowerAltName !== $nameLower) {
                                                if(!in_array($altName, $actors[$nameLower]['alternative_names'])) {
                                                    $actors[$nameLower]['alternative_names'][] = $altName;
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
                                        if(!array_key_exists('birth_date', $actors[$nameLower])) {
                                            $actors[$nameLower]['birth_date'] = $birthDate;
                                        } else {
                                            if(strlen($birthDate) > strlen($actors[$nameLower]['birth_date'])) {
                                                $actors[$nameLower]['birth_date'] = $birthDate;
                                            }
                                        }
                                    }
                                    if($deathDate !== null && !empty($deathDate)) {
                                        if(!array_key_exists('death_date', $actors[$nameLower])) {
                                            $actors[$nameLower]['death_date'] = $deathDate;
                                        } else {
                                            if(strlen($deathDate) > strlen($actors[$nameLower]['death_date'])) {
                                                $actors[$nameLower]['death_date'] = $deathDate;
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
                                                if (!array_key_exists('external_authorities', $actors[$nameLower])) {
                                                    $actors[$nameLower]['external_authorities'] = [];
                                                }
                                                if (!in_array($id, $actors[$nameLower]['external_authorities'])) {
                                                    $actors[$nameLower]['external_authorities'][$externalAuthoritySource] = $id;
                                                }
                                            }
                                        }
                                    }

                                    if(!array_key_exists('works', $actors[$nameLower])) {
                                        $actors[$nameLower]['works'] = [];
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

                                    $actors[$nameLower]['works'][$objectId] = $work;
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
            //Merge actors with the same external ID but with a different name
            $mergedActors = [];
            $alreadyEncountered = [];
            foreach($actors as $name => $actor) {
                if(array_key_exists($name, $alreadyEncountered)) {
                    continue;
                }
                $alreadyEncountered[$name] = $name;
                $actorValue = $actor;

                if(array_key_exists('external_authorities', $actor)) {
                    foreach($actor['external_authorities'] as $authority => $id) {
                        foreach($actors as $name1 => $actor1) {
                            if($name1 !== $name && !array_key_exists($name1, $alreadyEncountered)) {
                                if (array_key_exists('external_authorities', $actor1)) {
                                    foreach($actor1['external_authorities'] as $authority1 => $id1) {
                                        if($id === $id1) {
                                            $alreadyEncountered[$name1] = $name1;
                                            $actorValue = $this->mergeActors($actor, $actor1);
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $mergedActors[$name] = $actorValue;
            }

            //Merge actors with the same name but with '(' or ')' in one name and not in the other
            $mergedActors2 = [];
            $alreadyEncountered = [];
            foreach($mergedActors as $name => $actor) {
                if(array_key_exists($name, $alreadyEncountered)) {
                    continue;
                }
                $actorValue = $actor;

                //Check if this name already exists but with '(' or ')' in the name
                if(strpos($name, '(') !== false || strpos($name, ')') !== false || strpos($name, '-') !== false) {
                    $nameStripped = str_replace('(', '', $name);
                    $nameStripped = str_replace(')', '', $nameStripped);
                    $nameStripped = str_replace('-', ' ', $nameStripped);
                    if(array_key_exists($nameStripped, $actors) && !array_key_exists($nameStripped, $alreadyEncountered)) {
                        $alreadyEncountered[$nameStripped] = $nameStripped;
                        $actorValue = $this->mergeActors($actors[$nameStripped], $actor);
                    }
                }
                $mergedActors2[$name] = $actorValue;
            }

            //Merge actors where one of the alternative names matches with each other
            $mergedActors3 = [];
            $alreadyEncountered = [];
            foreach($mergedActors2 as $name => $actor) {
                if(array_key_exists($name, $alreadyEncountered)) {
                    continue;
                }
                foreach($actor['alternative_names'] as $altName) {
                    $altNameStripped = str_replace('(', '', $altName);
                    $altNameStripped = str_replace(')', '', $altNameStripped);
                    $altNameStripped = str_replace('-', ' ', $altNameStripped);
                    $altNameStripped = strtolower($altNameStripped);
                    foreach($mergedActors2 as $name1 => $actor1) {
                        if (array_key_exists($name1, $alreadyEncountered)) {
                            continue;
                        }
                        foreach($actor1['alternative_names'] as $altName1) {
                            $altNameStripped1 = str_replace('(', '', $altName1);
                            $altNameStripped1 = str_replace(')', '', $altNameStripped1);
                            $altNameStripped1 = str_replace('-', ' ', $altNameStripped1);
                            $altNameStripped1 = strtolower($altNameStripped1);
                            if($altNameStripped === $altNameStripped1) {
                                $alreadyEncountered[$name1] = $name1;
                                $actor = $this->mergeActors($actor, $actor1);
                                break;
                            }
                        }
                    }
                }
                $mergedActors3[$name] = $actor;
            }

            //Once again merge actors with the same external ID but with a different name
            $mergedActors4 = [];
            $alreadyEncountered = [];
            foreach($mergedActors3 as $name => $actor) {
                if(array_key_exists($name, $alreadyEncountered)) {
                    continue;
                }
                $actorValue = $actor;

                if(array_key_exists('external_authorities', $actor)) {
                    foreach($actor['external_authorities'] as $authority => $id) {
                        foreach($mergedActors3 as $name1 => $actor1) {
                            if($name1 !== $name && !array_key_exists($name1, $alreadyEncountered)) {
                                if (array_key_exists('external_authorities', $actor1)) {
                                    foreach($actor1['external_authorities'] as $authority1 => $id1) {
                                        if($id === $id1) {
                                            $alreadyEncountered[$name1] = $name1;
                                            $actorValue = $this->mergeActors($actor, $actor1);
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $mergedActors4[$name] = $actorValue;
            }

            //Remove all duplicates in the alternative_names list
            $mergedActors5 = [];
            foreach($mergedActors4 as $name => $actor) {
                $actor['alternative_names'] = array_unique($actor['alternative_names']);
                //Filter out the primary name
                if(in_array($name, $actor['alternative_names'])) {
                    $actor['alternative_names'] = array_values(array_diff($actor['alternative_names'], [$name]));
                }
                //Filter out the name with comma
                if(array_key_exists('name_with_comma', $actor)) {
                    if (in_array($actor['name_with_comma'], $actor['alternative_names'])) {
                        $actor['alternative_names'] = array_values(array_diff($actor['alternative_names'], [$actor['name_with_comma']]));
                    }
                }
                $newAltNames = [];
                foreach($actor['alternative_names'] as $altName) {
                    if(strtolower($name) !== strtolower($altName)) {
                        $newAltNames[] = $altName;
                    }
                }
                if(empty($newAltNames)) {
                    unset($actor['alternative_names']);
                } else {
                    $actor['alternative_names'] = $newAltNames;
                }
                $mergedActors5[$name] = $actor;
            }

            //Add 's' at the end of 'Anonieme Meester'
            if(array_key_exists('anonieme meester', $mergedActors5)) {
                if(array_key_exists('anonieme meesters', $mergedActors5)) {
                    $mergedActors5['anonieme meesters'] = $this->mergeActors($mergedActors5['anonieme meesters'], $mergedActors5['anonieme meester']);
                } else {
                    $mergedActors5['anonieme meesters'] = $mergedActors5['anonieme meester'];
                }
                unset($mergedActors5['anonieme meester']);
            }

            //Use primary_name as key
            $mergedActors6 = [];
            foreach($mergedActors5 as $name => $actor) {
                $mergedActors6[$actor['primary_name']] = $actor;
                unset($mergedActors6[$actor['primary_name']]['primary_name']);
            }

            ksort($mergedActors6);
            $fp = fopen($filename, 'w');
            fwrite($fp, json_encode($mergedActors6, JSON_PRETTY_PRINT));
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

    private function mergeActors($actor, $actor1) {
        $mergedActor = array_merge($actor1, $actor);
        $mergedActor['alternative_names'] = array_merge($actor['alternative_names'], $actor1['alternative_names']);
        if(array_key_exists('birth_date', $actor) && array_key_exists('birth_date', $actor1)) {
            if (strlen($actor1['birth_date']) > strlen($actor['birth_date'])) {
                $mergedActor['birth_date'] = $actor1['birth_date'];
            }
        }
        if(array_key_exists('death_date', $actor) && array_key_exists('death_date', $actor1)) {
            if (strlen($actor1['death_date']) > strlen($actor['death_date'])) {
                $mergedActor['death_date'] = $actor1['death_date'];
            }
        }
        if(array_key_exists('external_authorities', $actor) && array_key_exists('external_authorities', $actor1)) {
            $mergedActor['external_authorities'] = array_merge($actor1['external_authorities'], $actor['external_authorities']);
        }
        if(array_key_exists('works', $actor) && array_key_exists('works', $actor1)) {
            $mergedActor['works'] = array_merge($actor['works'], $actor1['works']);
        }
        return $mergedActor;
    }
}
