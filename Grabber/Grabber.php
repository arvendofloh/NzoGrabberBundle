<?php

/*
 * NzoGrabberExtension file.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nzo\GrabberBundle\Grabber;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Grabber
 * @package Nzo\GrabberBundle\Grabber
 */
class Grabber
{
    private $url;
    private $domainUrl;
    private $notScannedUrlsTab;
    private $scannedUrlsTab;
    private $extensionTab;
    private $client;
    private $exclude;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * @param string $url
     * @param array|null $notScannedUrlsTab
     * @param null $exclude
     * @param array|null $extensionTab
     * @return array
     */
    public function grabUrls($url, array $notScannedUrlsTab = null, $exclude = null, array $extensionTab = null)
    {
        $this->cleanArray();

        $this->url = $url;
        $this->notScannedUrlsTab = $notScannedUrlsTab;
        $this->extensionTab = $extensionTab;
        $this->scannedUrlsTab[] = $this->url;
        $this->domainUrl = $this->getDomain($this->url);
        $this->exclude = $exclude;

        $i = 0;

        while (count($this->scannedUrlsTab) > $i) {
            $this->crawler($this->scannedUrlsTab[$i]);
            $i++;
        }

        return $this->scannedUrlsTab;
    }


    /**
     * @param string $urlsTab
     * @return array
     */
    public function addHost($urlsTab)
    {
        foreach ($urlsTab as $val) {
            $sub = substr($val, 0, 7);
            if ('http://' === $sub || 'https:/' === $sub) {
                $this->scannedUrlsTab[] = $val;
            } else {
                if ($val[0] === '/') {
                    $this->scannedUrlsTab[] = $this->url . $val;
                } else {
                    $this->scannedUrlsTab[] = $this->url . '/' . $val;
                }
            }
        }

        return $this->scannedUrlsTab;
    }


    /**
     * @param string $url
     * @return array
     */
    public function grabExtrat($url)
    {
        $this->cleanArray();
        $this->url = $this->cleanUrl($url);
        $crawler = $this->client->request('GET', $this->url);
        $this->url = $this->getDomain($this->url);
        $this->addHostCss($crawler->filter('link[href]')->extract(array('href')));
        $this->addHost($crawler->filter('img[src]')->extract(array('src')));
        $this->addHost($crawler->filter('script[src]')->extract(array('src')));

        return $this->scannedUrlsTab;
    }

    /**
     * @param string $url
     * @return string
     */
    public function getDomain($url)
    {
        $url = str_replace('://www.', '://', $url);

        return parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
    }

    public function cleanArray()
    {
        $this->notScannedUrlsTab = array();
        $this->scannedUrlsTab = array();
        $this->extensionTab = array();
    }

    /**
     * @param string $link
     * @return bool
     */
    public function notInExculde($link)
    {
        if (empty($this->exclude)) {
            return true;
        }

        return strpos($link, $this->exclude) === false;
    }

    /**
     * @param string $newUrl
     * @return bool
     */
    private function crawler($newUrl)
    {
        try {
            $response = $this->client->request('GET', $newUrl, [
                'allow_redirects' => true
            ]);
            $html = $response->getBody()->getContents();
            
        } catch (\Exception $e) {
            return false;
        }

        if($html){
            $crawler = new Crawler($html, $newUrl);
        } else {
            return false;
        }

        foreach ($crawler->filter('a[href]')->links() as $domElement) {
            $link = $this->cleanUrl($domElement->getUri());
            if ($this->testExistanceScanned($link)
                && $this->testExistanceNotScanned($link)
                && $this->testDomain($link)
                && $this->testExtension($link)
                && $this->notInExculde($link)
            ) {
                $this->scannedUrlsTab[] = $link;
            }
        }

        return true;
    }

    /**
     * @param string $link
     * @return bool
     */
    private function testDomain($link)
    {
        return $this->getDomain($link) === $this->domainUrl;
    }

    /**
     * @param string $link
     * @return bool
     */
    private function testExistanceScanned($link)
    {
        $link = str_replace('://www.', '://', $link);
        $stringUrl = substr($link, 0, -1);
        $verifChar = substr($link, -1) === '/';

        foreach ($this->scannedUrlsTab as $val) {
            $val = str_replace('://www.', '://', $val);
            if ($link === $val || ($verifChar && $stringUrl === $val) || (!$verifChar && $link . '/' === $val)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $link
     * @return bool
     */
    private function testExistanceNotScanned($link)
    {
        $link = str_replace('://www.', '://', $link);
        if (empty($this->notScannedUrlsTab)) {
            return true;
        }
        $stringUrl = substr($link, 0, -1);
        $verifChar = substr($link, -1) === '/';
        foreach ($this->notScannedUrlsTab as $val) {
            $val = str_replace('://www.', '://', $val);
            if ($link === $val || ($verifChar && $stringUrl === $val) || (!$verifChar && $link . '/' === $val)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $link
     * @return bool
     */
    private function testExtension($link)
    {
        if (empty($this->extensionTab)) {
            return true;
        }

        if (substr($link, -1) === '/' || substr($link, -1) === '#') {
            $link = substr($link, 0, -1);
        }

        foreach ($this->extensionTab as $extension) {
            if (strtolower(substr($link, -(strlen($extension) + 1))) === '.' . strtolower($extension)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $link
     * @return string
     */
    private function cleanUrl($link)
    {
        return (substr($link, -1) === '#') ? substr($link, 0, -1) : $link;
    }
}
