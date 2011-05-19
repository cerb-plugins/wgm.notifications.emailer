<?php
class WgmNotifEmailerCron extends CerberusCronPageExtension {
	const ID = 'wgm.notifications.emailer.cron';
	
	public function run() {
		$logger = DevblocksPlatform::getConsoleLog('Notifications Emailer');
		$db = DevblocksPlatform::getDatabaseService();
		$url_writer = DevblocksPlatform::getUrlService();
		
		$logger->info("Started");
		
		// Load from extension prefs
		$last_checktime = DAO_DevblocksExtensionPropertyStore::get(self::ID,'last_checktime',0);
		
		$workers = DAO_Worker::getAllActive();
		
		// [TODO] This should use DAO
		$workers_with_notifications = $db->GetArray(
			sprintf("SELECT worker_id, count(id) AS hits ".
				"FROM notification ".
				"WHERE created_date > %d ".
				"AND is_read = 0 ".
				"GROUP BY worker_id",
				$last_checktime
			)
		);
		
		$helpdesk_title = DevblocksPlatform::getPluginSetting('cerberusweb.core','helpdesk_title','the helpdesk');
		
		// Loop through workers with notifications and send a digest
		foreach($workers_with_notifications as $row) {
			$worker_id = intval($row['worker_id']);
			$count = intval($row['hits']);
			
			if(null == ($worker = @$workers[$worker_id]))
				continue;
			
			$subject = sprintf("You have %d new notification%s on %s.",
				$count,
				(1==$count ? '' : 's'),
				$helpdesk_title
			);
			$body = '';
			
//			var_dump($worker_id);
//			var_dump($subject);

			// [TODO] Decide if we're sending a short (IM/SMS) or long (email) notification
			
			$body .= sprintf("%s\n%s\n\n",
				$helpdesk_title,
				$url_writer->writeNoProxy('c=profiles&k=worker&id=me&tab=notifications', true)
			); 
			
			$notifications = DAO_Notification::getWhere(sprintf("%s = %d AND %s = %d AND %s > %d",
				DAO_Notification::WORKER_ID,
				$worker_id,
				DAO_Notification::IS_READ,
				0,
				DAO_Notification::CREATED_DATE,
				$last_checktime
			));
			
			foreach($notifications as $notification_id => $notification) { /* @var $notification Model_WorkerEvent */
				$body .= sprintf("%s (%s)\n%s\n\n",
					$notification->message,
					// [TODO] Make this public as a DevblocksPlatform::str___() function
					_DevblocksTemplateManager::modifier_devblocks_prettytime($notification->created_date),
					//$notification->url
					$url_writer->writeNoProxy('c=preferences&a=redirectRead&id='.$notification->id, true)
				);
			}
			
			CerberusMail::quickSend($worker->email, $subject, $body);
			
//			var_dump($body);
		}
		
		// Persist
		DAO_DevblocksExtensionPropertyStore::put(self::ID, 'last_checktime', time());
		
		$logger->info("Finished");
	}
	
	public function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		//$tpl->display('devblocks:wgm.notifications.emailer::cron/config.tpl');
	}
	
	public function saveConfigurationAction() {
	}
}