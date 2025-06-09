<?php
return [   
	'notification_refresh_time' => 40000, //40s
	'asset_v' => 16,
	'doc_img_path' => 'doc_imgs',
	'verify_email' => env('VERIFY_EMAIL'),
	'landing_page' => env('DEFAULT_LANDING_PAGE', 'login'),
];
