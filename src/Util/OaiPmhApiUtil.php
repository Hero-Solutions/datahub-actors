<?php

use Phpoaipmh\Client;
use Phpoaipmh\Endpoint;
use Phpoaipmh\Exception\HttpException;
use Phpoaipmh\Exception\OaipmhException;
use Phpoaipmh\HttpAdapter\CurlAdapter;

class OaiPmhApiUtil
{
    public static function connect($url, $overrideCertificateAuthorityFile, $sslCertificateAuthorityFile, $username = null, $password = null)
    {
        $oaiPmhEndpoint = null;
        try {
            $curlAdapter = new CurlAdapter();
            if($username !== null && $password !== null) {
                $curlOpts = array(
                    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                    CURLOPT_USERPWD => $username . ':' . $password
                );
            } else {
                $curlOpts = [];
            }
            if ($overrideCertificateAuthorityFile) {
                $curlOpts[CURLOPT_CAINFO] = $sslCertificateAuthorityFile;
                $curlOpts[CURLOPT_CAPATH] = $sslCertificateAuthorityFile;
            }
            $curlAdapter->setCurlOpts($curlOpts);
            $oaiPmhClient = new Client($url, $curlAdapter);
            $oaiPmhEndpoint = new Endpoint($oaiPmhClient);
        } catch(OaipmhException $e) {
            if($e->getOaiErrorCode() == 'noRecordsMatch') {
                echo 'No records to process, exiting.' . PHP_EOL;
            } else {
                echo 'OAI-PMH error (1): ' . $e . PHP_EOL;
            }
        }
        catch(HttpException $e) {
            echo 'OAI-PMH error (2): ' . $e . PHP_EOL;
        }
        catch(Exception $e) {
            echo 'OAI-PMH error (3): ' . $e . PHP_EOL;
        }
        return $oaiPmhEndpoint;
    }
}
