<?php

namespace Detain\MyAdminHyperv;

use Detain\Hyperv\Hyperv;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminHyperv
 */
class Plugin {

	public static $name = 'HyperV VPS';
	public static $description = 'Allows selling of HyperV VPS Types.  Microsoft Hyper-V, codenamed Viridian and formerly known as Windows Server Virtualization, is a native hypervisor; it can create virtual machines on x86-64 systems running Windows. Starting with Windows 8, Hyper-V superseded Windows Virtual PC as the hardware virtualization component of the client editions of Windows NT. A server computer running Hyper-V can be configured to expose individual virtual machines to one or more networks.  More info at https://www.microsoft.com/en-us/cloud-platform/server-virtualization';
	public static $help = '';
	public static $module = 'vps';
	public static $type = 'service';

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
			//self::$module.'.activate' => [__CLASS__, 'getActivate'],
			self::$module.'.deactivate' => [__CLASS__, 'getDeactivate'],
			self::$module.'.queue' => [__CLASS__, 'getQueue'],
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getActivate(GenericEvent $event) {
		$serviceClass = $event->getSubject();
		if ($event['type'] == get_service_define('HYPERV')) {
			myadmin_log(self::$module, 'info', 'Hyperv Activation', __LINE__, __FILE__);
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getDeactivate(GenericEvent $event) {
		if ($event['type'] == get_service_define('HYPERV')) {
			myadmin_log(self::$module, 'info', self::$name.' Deactivation', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Credentials', 'vps_hyperv_password', 'HyperV Administrator Password:', 'Administrative password to login to the HyperV server', $settings->get_setting('VPS_HYPERV_PASSWORD'));
		$settings->add_text_setting(self::$module, 'Slice Costs', 'vps_slice_hyperv_cost', 'HyperV VPS Cost Per Slice:', 'HyperV VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_HYPERV_COST'));
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_vps_hyperv_server', 'HyperV NJ Server', NEW_VPS_HYPERV_SERVER, 11, 1);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_hyperv', 'Out Of Stock HyperV Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_HYPERV'), ['0', '1'], ['No', 'Yes']);
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueue(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('HYPERV')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue '.ucwords(str_replace('_', ' ', $vps['action'])), __LINE__, __FILE__);
			$queue_calls = self::getQueueCalls();
			$vps = $event->getSubject();
			$calls = $queue_calls[$vps['action']];
			$server_info = $vps['server_info'];
			foreach ($calls as $call) {
				myadmin_log(self::$module, 'info', $vps['server_info']['vps_name'].' '.$call.' '.$vps['vps_hostname'].'(#'.$vps['vps_id'].'/'.$vps['vps_vzid'].')', __LINE__, __FILE__);
				try {
					$soap = new SoapClient(self::getSoapClientUrl($vps['server_info']['vps_ip']), self::getSoapClientParams());
					$response = $soap->$call(self::getDefaultCallParams($vps));
				} catch (Exception $e) {
					$msg = $vps['server_info']['vps_name'].' '.$call.' '.$vps['vps_hostname'].'(#'.$vps['vps_id'].'/'.$vps['vps_vzid'].') Caught exception: '.$e->getMessage();
					echo $msg.PHP_EOL;
					myadmin_log(self::$module, 'error', $msg, __LINE__, __FILE__);
				}
			}
			$event->stopPropagation();
		}
	}

	public static function getQeueueCalls() {
		return [
			'restart' => ['Reboot'],
			'enable' => ['TurnON'],
			'start' => ['TurnON'],
			'delete' => ['TurnOff'],
			'stop' => ['TurnOff'],
			'destroy' => ['TurnOff', 'DeleteVM'],
			'reinstall_os' => ['TurnOff', 'DeleteVM'],
		];
	}

	public static function getDefaultCallParams($vps) {
		return [
			'vmId' => $vps['vps_vzid'],
			'hyperVAdmin' => 'Administrator',
			'adminPassword' => $vps['server_info']['vps_root']
		];
	}

	/**
	 * gets the connection URL for the SoapClient
	 *
	 * @param string $address the ip address or domain name of the remote APi server
	 * @return array an array of the connection parameters for Soapclient
	 */
	public static function getSoapClientUrl($address) {
		return 'https://'.$address.'/HyperVService/HyperVService.asmx?WSDL';
	}

	/**
	 * gets the preferred connection parameter settings for the SoapClient
	 *
	 * @return array an array of the connection parameters for Soapclient
	 */
	public static function getSoapClientParams() {
		return [
			'encoding' => 'UTF-8',
			'verifypeer' => FALSE,
			'verifyhost' => FALSE,
			'soap_version' => SOAP_1_2,
			'trace' => 1,
			'exceptions' => 1,
			'connection_timeout' => 600,
			'stream_context' => stream_context_create([
				'ssl' => [
					'ciphers' => 'RC4-SHA',
					'verify_peer' => FALSE,
					'verify_peer_name' => FALSE
			]])
		];
	}
}
