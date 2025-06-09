<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	DB::beginTransaction();
    	DB::statement("SET FOREIGN_KEY_CHECKS = 0");

    	$canned_responses = array(
          array('id' => '2','name' => 'Rate us','message' => 'Dear {customer_name}

        Please add a review & 5-star rating, it helps us. Thank you in advance.','only_for_admin' => '0','created_at' => '2020-10-19 08:33:41','updated_at' => '2020-10-19 08:33:41'),
          array('id' => '3','name' => 'server info','message' => 'Dear {customer_name}

        Please provide server information for us to debug the issue','only_for_admin' => '0','created_at' => '2020-10-19 08:33:41','updated_at' => '2020-10-19 08:33:41'),
          array('id' => '4','name' => 'Support expired','message' => 'Dear {customer_name}

        Your support has expired. Kindly renew it','only_for_admin' => '0','created_at' => '2020-10-19 08:33:41','updated_at' => '2020-10-19 08:33:41')
        );

        DB::table('canned_responses')->insert($canned_responses);

        $licenses = array(
          array('id' => '1','user_id' => '3','product_id' => '1','license_key' => \Crypt::encryptString('Dummy-License-1-897Y'),'source_id' => '1','purchased_on' => '2020-08-25 15:45:46','support_expires_on' => '2022-02-24 07:45:46','expires_on' => NULL,'created_at' => '2020-10-19 09:29:49','updated_at' => '2020-10-19 09:29:49')
        );

        DB::table('licenses')->insert($licenses);


        $products = array(
          array('id' => '1','name' => 'Product1','description' => 'This is a test product description','user_id' => '1','is_active' => '1','created_at' => '2020-10-19 08:24:18','updated_at' => '2020-10-19 08:24:18'),
          array('id' => '2','name' => 'Product 2','description' => 'This is a test product description','user_id' => '1','is_active' => '1','created_at' => '2020-10-19 08:26:00','updated_at' => '2020-10-19 08:26:00')
        );

        DB::table('products')->insert($products);


        /* `support`.`product_sources` */
		$product_sources = array(
  			array('id' => '1','product_id' => '1','source_id' => '1','product_id_in_source' => '1'),
  			array('id' => '2','product_id' => '2','source_id' => '2','product_id_in_source' => '2')
			);

		DB::table('product_sources')->insert($product_sources);


		/* `support`.`product_support_agents` */
		$product_support_agents = array(
		  array('id' => '1','product_id' => '1','user_id' => '1'),
		  array('id' => '2','product_id' => '2','user_id' => '1')
		);

		DB::table('product_support_agents')->insert($product_support_agents);


		$sources = array(
		  array('id' => '1','name' => 'Envato','source_type' => 'envato','web_url' => NULL,'woo_consumer_key' => NULL,'woo_consumer_secret' => NULL,'envato_token' => '7nYe4GpZXMSh57YpjMsq','description' => 'For envato website','is_enabled' => '1','source_other_info' => '{"author_username":"codecanyonusername","items":{"1":"Codecanyon Products 1","2":"Themeforest product2", "3":"Themeforest product 3"}}','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 08:13:22'),
		  array('id' => '2','name' => 'WooCommerce Licensing','source_type' => 'woolicensing','web_url' => 'https://yourwebsite.com','woo_consumer_key' => NULL,'woo_consumer_secret' => NULL,'envato_token' => NULL,'description' => 'For WooCommerce website that uses WooLicensing software','is_enabled' => '1','source_other_info' => NULL,'created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 08:01:09')
		);

		DB::table('sources')->insert($sources);


		/* `support`.`systems` */
        $systems = array(
          array('key' => 'ticket','value' => '2','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 09:35:54'),
          array('key' => 'ticket_prefix','value' => '#','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 09:27:38'),
          array('key' => 'ticket_instruction','value' => '<ol>
        <li>This is some dummy instruction message to customers</li>
        <li>You can modify it from superadmin login</li>
        <li>Also, HTML can be added here.</li>
        </ol>
        <hr />
        <p>&nbsp;</p>','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 09:28:17'),
          array('key' => 'cust_new_ticket_app_notif','value' => '0','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('key' => 'cust_new_ticket_mail_notif','value' => '0','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('key' => 'agent_replied_to_ticket_app_notif','value' => '0','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('key' => 'agent_replied_to_ticket_mail_notif','value' => '0','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('key' => 'agent_assigned_ticket_app_notif','value' => '0','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('key' => 'agent_assigned_ticket_mail_notif','value' => '0','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('key' => 'cust_replied_to_ticket_app_notif','value' => '0','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('key' => 'cust_replied_to_ticket_mail_notif','value' => '0','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('key' => 'is_public_ticket_enabled','value' => '1','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 09:27:38'),
          array('key' => 'default_ticket_type','value' => 'private','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30')
        );

        DB::table('systems')->insert($systems);


        $tickets = array(
          array('id' => '1','product_id' => '1','license_id' => '1','user_id' => '3','ticket_ref' => '#0001','subject' => 'What is Lorem Ipsum? Where does it come from?','message' => '<h2>What is Lorem Ipsum?</h2>
        <p><strong>Lorem Ipsum</strong>&nbsp;is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
        <p>&nbsp;</p>
        <h2>Where does it come from?</h2>
        <p>Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of "de Finibus Bonorum et Malorum" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, "Lorem ipsum dolor sit amet..", comes from a line in section 1.10.32.</p>','other_info' => NULL,'status' => 'new','is_public' => '0','created_at' => '2020-10-19 09:30:56','updated_at' => '2020-10-19 09:37:22'),
          array('id' => '2','product_id' => '1','license_id' => '1','user_id' => '3','ticket_ref' => '#0002','subject' => 'This is a test public ticket. Any other customers can see this ticket. But all user information is not displayed to public','message' => '<h2>What is Lorem Ipsum?</h2>
        <p><strong>Lorem Ipsum</strong>&nbsp;is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>','other_info' => 'qwqw','status' => 'new','is_public' => '1','created_at' => '2020-10-19 09:35:54','updated_at' => '2020-10-19 09:37:56')
        );

        DB::table('tickets')->insert($tickets);


        $ticket_comments = array(
          array('id' => '1','ticket_id' => '1','user_id' => '1','comment' => '<p>Dear customer<br /><br />Please provide server information for us to debug the issue</p>','created_at' => '2020-10-19 09:36:59','updated_at' => '2020-10-19 09:36:59'),
          array('id' => '2','ticket_id' => '2','user_id' => '1','comment' => '<p>Dear customer<br /><br />Your support has expired. Kindly renew it</p>','created_at' => '2020-10-19 09:37:08','updated_at' => '2020-10-19 09:37:08'),
          array('id' => '3','ticket_id' => '1','user_id' => '1','comment' => '<p>Dear customer<br /><br />Please add a review &amp; 5-star rating, it helps us. Thank you in advance.</p>','created_at' => '2020-10-19 09:37:22','updated_at' => '2020-10-19 09:37:22'),
          array('id' => '4','ticket_id' => '2','user_id' => '1','comment' => '<p>Dear customer<br /><br />Please provide server information for us to debug the issue</p>','created_at' => '2020-10-19 09:37:40','updated_at' => '2020-10-19 09:37:40'),
          array('id' => '5','ticket_id' => '2','user_id' => '1','comment' => '<div>
        <p><strong>Lorem Ipsum</strong>&nbsp;is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
        </div>
        <div>&nbsp;</div>','created_at' => '2020-10-19 09:37:56','updated_at' => '2020-10-19 09:37:56')
        );

        DB::table('ticket_comments')->insert($ticket_comments);


        $ticket_support_agents = array(
          array('id' => '1','ticket_id' => '1','user_id' => '1','created_at' => '2020-10-19 09:30:56','updated_at' => '2020-10-19 09:30:56'),
          array('id' => '2','ticket_id' => '2','user_id' => '2','created_at' => '2020-10-19 09:35:54','updated_at' => '2020-10-19 09:35:54')
        );

        DB::table('ticket_support_agents')->insert($ticket_support_agents);



        $users = array(
          array('id' => '1','name' => 'Superadmin','email' => 'superadmin@example.com','email_verified_at' => '2020-10-19 13:29:15','password' => '$2y$10$mGNSBv9c2WIzpatpp9bYCuT74zXsmGzMJr/XmfhMX7yUUpAqGYeie','remember_token' => NULL,'role' => 'admin','created_at' => '2020-10-19 07:59:30','updated_at' => '2020-10-19 07:59:30'),
          array('id' => '2','name' => 'Support agent','email' => 'agent@example.com','email_verified_at' => '2020-10-19 14:01:10','password' => '$2y$10$Y5LJPNOz.loEbLi06.Gqw.fIWgru3YmLfZy3roZX9dUBiFwOSeXnW','remember_token' => NULL,'role' => 'support_agent','created_at' => '2020-10-19 08:30:30','updated_at' => '2020-10-19 08:30:30'),
          array('id' => '3','name' => 'customer','email' => 'customer@example.com','email_verified_at' => '2020-10-19 14:01:15','password' => '$2y$10$ex.hF38tUG0H0w1FSninJ.F3aFwj26VUdVGDfjvQx6ai9s48cb4xK','remember_token' => NULL,'role' => 'customer','created_at' => '2020-10-19 08:30:59','updated_at' => '2020-10-19 08:30:59')
        );

        DB::table('users')->insert($users);

        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        DB::commit();
    }
}
