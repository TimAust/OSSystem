<?php
/**
 * @package   OSSystem
 * @contact   www.alledia.com, support@alledia.com
 * @copyright 2013-2014 Alledia.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 */

namespace Alledia\OSSystem\Joomla;

use Alledia\Framework\Joomla\Extension\Generic as GenericExtension;
use Alledia\Framework\Network\Request;
use JFactory;

// No direct access
defined('_JEXEC') or die();

/**
 * Metatags Container Factory Class
 *
 * @since  1.0
 */
class Tracker
{
    private static $instance;

    protected $secureHash = '4dc5768203f6f16fe214d3f2cefe60ec876a4136de7ae12e6e54d610bfa462';

    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Say to the server that the tracker is disabled
     *
     * @return bool True if sent with success
     */
    public function disable()
    {
        $clientHash = $this->getAnonymousHash();

        $request = new Request;
        $response = $request->post(
            // 'http://deploy.ostraining.com/tracker/api/disable/' . $this->secureHash,
            'http://devbox.vm:8001/tracker/api/disable/' . $this->secureHash,
            array('client_hash' => $clientHash)
        );

        var_dump($response);

        return false;
    }

    /**
     * Get information about the user's site and server setup and
     * send anonymously to the server
     *
     * @return bool True if sent with success
     */
    public function track()
    {
        $data = $this->getTrackerData();

        $request = new Request;
        $response = $request->post(
            // 'http://deploy.ostraining.com/tracker/api/update/' . $this->secureHash,
            'http://devbox.vm:8001/tracker/api/update/' . $this->secureHash,
            array('info' => $data)
        );

        var_dump($response);

        return false;
    }

    /**
     * Get an anonymous hash to store unique info from each joomla install.
     *
     * @return [type] [description]
     */
    protected function getAnonymousHash()
    {
        return @sha1($_SERVER['HTTP_HOST'] . $_SERVER['SERVER_ADDR'] . $_SERVER['SCRIPT_FILENAME']);
    }

    /**
     * Get information about the user's site and server setup
     *
     * @return string The data
     */
    protected function getTrackerData()
    {
        $db     = JFactory::getDBO();
        $config = JFactory::getConfig();

        $data = array();

        // Anonymous hash
        $data['hash'] = $this->getAnonymousHash();

        // Joomla
        $data['joomla_version']         = JVERSION;

        // Joomla Cache
        $cachingValues = array(
            0 => 'off',
            1 => 'conservative',
            2 => 'progressive'
        );
        $data['joomla_caching']         = $cachingValues[$config->get('caching')];
        $data['joomla_cache_handler']   = $config->get('cache_handler');
        $data['joomla_cache_time']      = $config->get('cachetime');

        unset($cachingValues);

        // Joomla Session
        $data['joomla_session_time']    = $config->get('lifetime');
        $data['joomla_session_handler'] = $config->get('session_handler');

        // Joomla SEO
        $data['joomla_sef']             = (int) $config->get('sef');
        $data['joomla_sef_rewrite']     = (int) $config->get('sef_rewrite');
        $data['joomla_sef_suffix']      = (int) $config->get('sef_suffix');
        $data['joomla_unicodeslugs']    = (int) $config->get('unicodeslugs');
        $data['joomla_robots']          = (int) $config->get('robots');

        // Joomla Server
        $sslValues = array(
            0 => 'off',
            1 => 'admin',
            2 => 'both'
        );
        $data['joomla_gzip']            = $config->get('gzip');
        $data['joomla_force_ssl']       = $sslValues[$config->get('force_ssl')];
        $data['joomla_ftp']             = (int) $config->get('ftp_enable');
        $data['joomla_proxy']           = (int) $config->get('proxy_enable');
        $data['joomla_mail']            = (int) $config->get('mailonline');
        $data['joomla_mailer']          = $config->get('mailer');

        unset($sslValues, $config);

        // PHP
        $data['php_version']            = @PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
        $data['php_version_id']         = @PHP_VERSION_ID;
        $data['php_extra_version']      = @PHP_EXTRA_VERSION;
        $data['php_zts']                = @PHP_ZTS;
        $data['php_maxpathlen']         = @PHP_MAXPATHLEN;

        $charSearchValues  = array("\n", "\r", "\s", "\t");
        $charReplaceValues = array('\n', '\r', '\s', '\t');
        $data['php_eol']                = @str_replace($charSearchValues, $charReplaceValues, PHP_EOL);

        $data['php_sapi']               = @php_sapi_name();
        $data['php_loaded_extensions']  = @get_loaded_extensions();

        unset($charSearchValues, $charReplaceValues);

        // DB
        preg_match('/([0-9]*\.[0-9]*\.[0-9]*)(.*)?/', $db->getVersion(), $dbVersion);
        $data['db_driver']              = $db->name;
        $data['db_version']             = $dbVersion[1];
        $data['db_extra_version']       = $dbVersion[2];

        // OS
        $data['os']                     = @php_uname('s');
        $data['os_release_name']        = @php_uname('r');
        $data['os_version_info']        = @php_uname('v');
        $data['os_machine_type']        = @php_uname('m');

        // Installed Extensions
        $query = $db->getQuery(true)
            ->select(array('extension_id', 'name', 'type', 'element', 'folder', 'client_id', 'enabled'))
            ->from('#__extensions')
            ->where('type <> ' . $db->quote('language'))
            ->where('type <> ' . $db->quote('file'));
        $db->setQuery($query);
        $extensions = $db->loadAssocList();

        unset($query);

        // Extensions versions
        foreach ($extensions as &$row) {
            // get extension from manifest as string, do not load the manifest
            $extension = new GenericExtension($row['element'], $row['type'], $row['folder']);
            $row['version'] = @$extension->manifest->version;
        }

        $data['extensions'] = $extensions;
        unset($extensions, $extension, $row);

        return json_encode($data);
    }
}
