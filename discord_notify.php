<?php

/**
 * @Brief header the next comment is our php header it describe our plugin and allow it to work
 */

/**
 * Plugin Name: Discord Notify
 * Plugin URI: https://ynov.com
 * Description: This plugin allows you to send a message to your Discord server when a new comment is posted.
 * Version: 1.0.0
 * Author: Louis Sasse & Baptiste Puig
 * Author URI: https://toto.com
 * License: GPL
 */

/**
 * @Brief class DiscordNotify will contain our plugin discord
 */
class DiscordNotify
{
  private $discordNotification;

  /**
   * @Brief DiscordNotify's constructor
   */
  public function __construct()
  {
    add_action('admin_menu', array($this, 'discordAddPage'));
    add_action('admin_init', array($this, 'discordNotificationInit'));
  }

  /**
   * @Brief add our discord notify a page
   * @return void
   */
  public function discordAddPage()
  {
    add_plugins_page(
      'Discord notify', // page_title
      'Discord notify', // menu_title
      'manage_options', // capability
      'discord-notify', // menu_slug
      array($this, 'discordAdminPage') // function

    );
  }

  /**
   * @Brief Generate our page
   * @return void
   */
  public function discordAdminPage()
  {
    $this->discordNotification = get_option('discord_notify_option_name'); ?>

    <div class="wrap">
      <h2>Discord notify</h2>
      <p>description</p>
      <?php settings_errors(); ?>

      <form method="post" action="options.php">
        <?php
        settings_fields('discordOptionGroup');
        do_settings_sections('discord-notify-admin');
        submit_button();
        ?>
      </form>
    </div>
<?php }

  /**
   * @Brief Initiate our parameters
   * @return void
   */
  public function discordNotificationInit()
  {
    register_setting(
      'discordOptionGroup', // option_group
      'discord_notify_option_name', // option_name
      array($this, 'discordNotifySanitize') // sanitize_callback
    );

    add_settings_section(
      'discordNotifySettingSection', // id
      'Settings', // title
      array($this, 'discord_notify_section_info'), // callback
      'discord-notify-admin' // page
    );

    add_settings_field(
      'webhook_0', // id
      'webhook', // title
      array($this, 'webhookCallback'), // callback
      'discord-notify-admin', // page
      'discordNotifySettingSection' // section
    );
  }

  /**
   * @Brief Will insert user's input in ou variable
   * @param $input
   * @return array
   */
  public function discordNotifySanitize($input)
  {
    $sanitary_values = array();
    if (isset($input['webhook_0'])) {
      $sanitary_values['webhook_0'] = sanitize_text_field($input['webhook_0']);
    }

    return $sanitary_values;
  }

  /**
   * @Brief Will enter the value inside our notification
   * @return void
   */
  public function webhookCallback()
  {
    printf(
      '<input class="regular-text" type="text" name="discord_notify_option_name[webhook_0]" id="webhook_0" value="%s">',
      isset($this->discordNotification['webhook_0']) ? esc_attr($this->discordNotification['webhook_0']) : ''
    );
  }
}
if (is_admin()) $discord_notify = new DiscordNotify();

/**
 * @Brief send the webhook on discord with the url passed by the user
 * @param $comment
 * @param $webhook_url
 * @return void
 */
function dnSendMessage($comment, $webhook_url)
{
  $json_data = discordTranslate($comment->comment_content);
  $obj = json_decode($json_data);


  $timestamp = date("c", strtotime("now"));
  $json_data = json_encode([
    "content" => $obj->data->translations[0]->translatedText,
    "username" => "Your wordpress ite",
    "tts" => false,
    "embeds" => [
      [
        "title" => "You got a comment",
        "type" => "rich",
        "description" => $comment->comment_author . " ( " . $comment->comment_author_email . " )",
        "url" => get_permalink($comment->comment_post_ID),
        "timestamp" => $timestamp,
        "color" => hexdec("33ffcc"),
        "author" => [
          "name" => "Your wordpress site",
          "url" => "http://37.187.55.30:8080/"
        ],
      ]
    ]
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $ch = curl_init($webhook_url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $response = curl_exec($ch);
  curl_close($ch);
}

/**
 * @Brief Call the google translate api and translate our comment in french
 * @param $message
 * @return bool|mixed|string
 */
function discordTranslate($message)
{
  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_URL => "https://google-translate1.p.rapidapi.com/language/translate/v2",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "q={$message}&target=fr",
    CURLOPT_HTTPHEADER => [
      "accept-encoding: application/gzip",
      "content-type: application/x-www-form-urlencoded",
      "x-rapidapi-host: google-translate1.p.rapidapi.com",
      "x-rapidapi-key: 0c5770c862mshab8ee8e7392cd33p135341jsn57edac0789a1"
    ],
  ]);

  $response = curl_exec($curl);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
    //echo $err;
    return $message;
  } else {
    return $response;
  }
}

/**
 * @Brief This function is called "on event" comment post
 * @param $comment_id
 * @param $comment_approved
 * @return void
 */
function dnNotifyComment($comment_id, $comment_approved)
{
  $discordNotification = get_option('discord_notify_option_name'); // Array of All Options
  $webhook_0 = $discordNotification['webhook_0']; // webhook

  if (!$comment_approved) {
    $comment = get_comment($comment_id);
    dnSendMessage($comment, $webhook_0);
  }
}

add_action('comment_post', 'dnNotifyComment', 10, 2);
