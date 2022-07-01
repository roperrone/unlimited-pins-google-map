<?php
/**
 * Plugin Name: Unlimited pins for Google Maps
 * Description: This plugin adds the latitude, longitude and video caption metadata to all blog posts 
 * Version: 1.0.0
 * Author: Romain Perrone
 */
namespace CustomGoogleMapsPlugin;

set_time_limit(3600);

if (!defined("ABSPATH")) {
  exit; // exit if accessed directly
}

class UnlimitedPins {

   /**
   * Constructor class of our plugin
   * 
   * @return void
   */ 
  public function __construct() { 
    /** Enqueue scripts */
    add_action( 'wp_enqueue_scripts', [$this, 'register_scripts'] );
    add_action( 'admin_enqueue_scripts', [$this, 'register_scripts'] );

    /** Add the blog post metadata */
    add_action( 'add_meta_boxes', [$this, 'add_wordpress_metadata']);
    add_action( 'save_post', [$this, 'unlimited_pins_save_postdata']);

    add_action( 'wp_body_open', [$this, 'add_custom_body']);
  }

  /**
   * Add our WP admin hooks
   * 
   * @return voidadd
   */ 
  public function load_admin() {
    add_action('admin_menu', [$this, 'add_plugin_options_page']);
    add_action('admin_init', [$this, 'add_plugin_settings']);
  }

  /**
   * Add our plugin's option page to the WP admin menu.
   * 
   * @return void
   */ 
  public function add_plugin_options_page() {
    add_options_page(
      'Unlimited pins Settings',
      'Unlimited pins Settings',
      'manage_options',
      'unlimited_pins',
      [$this, 'render_admin_page']
    );
  }

  /**
   * Render our plugin option page
   * 
   * @return void
   */ 
   public function render_admin_page() {
    ?>
    <div class="wrap">
      <h1>Unlimited Pins Settings</h1>
      <form method="post" action="options.php">
        <?php
          settings_fields('unlimited_pins');
          $this->render_info_settings();
          do_settings_sections('unlimited_pins');
          submit_button();
        ?>
      </form>
    </div>
    <?php
  }

  /**
   * Initialize our plugin settings
   * 
   * @return void
   */
  public function add_plugin_settings() {
    register_setting('unlimited_pins', 'unlimited_pins_options');

    add_settings_section(
      'unlimited_pins_settings',
      'Settings',
      null,
      'unlimited_pins'
    );

    add_settings_field(
      'api_key',
      'API Key',              
      [$this, 'render_api_key_settings'],
      'unlimited_pins',            
      'unlimited_pins_settings'                 
    );

    add_settings_section(
      'preview_settings',
      'Preview',
      [$this, 'render_maps'],
      'unlimited_pins'
    );

  }

  /**
   * Render the api key settings in wp-admin
   * 
   * @return void
   */
  function render_api_key_settings( $arg ) {
    printf(
      '<input type="text" id="api_key" name="unlimited_pins_options[api_key]" value="%s" />',
      get_option('unlimited_pins_options')['api_key'] ? esc_attr(get_option('unlimited_pins_options')['api_key']) : ''
    );
  }

  /**
   * Render the info settings in wp-admin
   * 
   * @return void
   */
  function render_info_settings() {
    ?>
    <span>This plugins uses the <a href="https://developers.google.com/maps/documentation/javascript/marker-clustering" target="_blank">Google Maps Marker Clustering API</a></span>

    <?php
  }

  /**
   * Render the map preview
   * 
   * @return void
   */
  function render_maps( $arg ) {
    ?>

    <div class="map-wrapper">
      <div id="map"></div>
    </div>

    <?php
  }

  /**
   * Retrieve the latitude, longitude, and caption of all available blog posts
   * 
   * @return array
   */
  function getLatLngIconCaption() {
    global $wpdb;

    $posts_with_meta = $wpdb->get_results( 
      "SELECT post_title, guid, group_concat(meta_value ORDER BY meta_key ASC SEPARATOR '\0') as metadata
      FROM $wpdb->postmeta as meta
      INNER JOIN $wpdb->posts as post ON meta.post_id = post.ID
      WHERE post_status = 'publish' AND post_type = 'post' AND meta_key IN ('_unlimited_pins_caption', '_unlimited_pins_icon', '_unlimited_pins_lat', '_unlimited_pins_lng')
      GROUP BY post_id
      ", ARRAY_A
    ); 

    if( !$posts_with_meta ) {
      return [];
    }

    return $posts_with_meta;
}

  /**
   * Register our CSS & JS files
   * 
   * @return void
   */
  function register_scripts() {

    // Enable Google Maps ONLY if an API Key is provided
    if(get_option('unlimited_pins_options')) {
      wp_enqueue_script( 'google-maps-marker-clusterer', "https://unpkg.com/@googlemaps/markerclustererplus/dist/index.min.js", array(), "1.2.6");
      
      wp_enqueue_script( 'unlimited-pins', plugins_url( '/js/index.js' , __FILE__ ), array('google-maps-marker-clusterer'), '1.2.6');

      wp_localize_script('unlimited-pins', 'unlimited_pins_config', array(
        'marker_path' => plugins_url( '/img' , __FILE__ ),
        'settings' => $this->getLatLngIconCaption(),
        'google_maps_apikey' => get_option('unlimited_pins_options')['api_key'] ? esc_attr(get_option('unlimited_pins_options')['api_key']) : '',
      ));
    }

    wp_enqueue_style( 'unlimited-pins-stylesheet', plugins_url( '/css/style.css', __FILE__));
  }

  /** 
   * Register Google Maps script
   * 
   * @return void
   */
  function add_custom_body() {
    if(!is_page('map-view'))
      return;

    $api_key = get_option('unlimited_pins_options')['api_key'] ? esc_attr(get_option('unlimited_pins_options')['api_key']) : '';
    echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . $api_key .'&callback=initMap&v=weekly" defer></script>';
  }

  /**
   * Add custom metadata to blog posts
   * 
   * @return void
   */
  function add_wordpress_metadata() {

    if ( current_user_can( 'manage_options' ) ) {
			add_meta_box( 'unlimited_pins_div_config', __( 'Google Maps Setup', 'unlimited-pins' ), array( $this, 'meta_box_html' ), 'post', 'normal', 'low' );
		}
  }

  /**
   * Define the metabox controls
   * 
   * @return void
   */
  function meta_box_html($post) {
    $caption = get_post_meta( $post->ID, '_unlimited_pins_caption', true );
    $lat = get_post_meta( $post->ID, '_unlimited_pins_lat', true );
    $lng = get_post_meta( $post->ID, '_unlimited_pins_lng', true );
    $icon = get_post_meta( $post->ID, '_unlimited_pins_icon', true );

?>
  <div class="components-base-control__field css-1kyqli5 e1puf3u2">
    <label class="components-base-control__label css-4dk55l e1puf3u1" for="unlimited_pins_field_caption">Map Caption</label>
    <input class="components-text-control__input" value="<?php echo esc_html($caption) ?>" name="unlimited_pins_field_caption" id="unlimited_pins_field_caption" type="text" autocomplete="off" spellcheck="false" placeholder="Portsmouth, New Hampshire, USA: 4K Drone Footage">
  </div>
  <div class="gutenberg-flex-latLng">
    <div class="components-base-control__field css-1kyqli5 e1puf3u2">
      <label class="components-base-control__label css-4dk55l e1puf3u1" for="unlimited_pins_field_lat">Latitude</label>
      <input class="components-text-control__input" value="<?php echo esc_html($lat) ?>" name="unlimited_pins_field_lat" id="unlimited_pins_field_lat" type="text" autocomplete="off" spellcheck="false" placeholder="43.0718">
    </div>
    <div class="components-base-control__field css-1kyqli5 e1puf3u2">
      <label class="components-base-control__label css-4dk55l e1puf3u1" for="unlimited_pins_field_lng">Longitude</label>
      <input class="components-text-control__input" value="<?php echo esc_html($lng) ?>" name="unlimited_pins_field_lng" id="unlimited_pins_field_lng" type="text" autocomplete="off" spellcheck="false" placeholder="-70.7626">
    </div>
    <div class="components-base-control__field css-1kyqli5 e1puf3u2">
      <label class="components-base-control__label css-4dk55l e1puf3u1" for="unlimited_pins_field_icon">Icon</label>
      <div class="components-input-control__container css-i4pl5y em5sgkm6" id="unlimited_pins_field_icon">
        <select class="components-select-control__input css-5c5yva e1mv6sxx1" name="unlimited_pins_field_icon">
        <?php
        
        $arr = [
          'default' => 'Default', 
          'bed' => 'Bed',
          'bike' => 'Bike',
          'coffee' => 'Coffee',
          'comedy' => 'Comedy', 
          'fork' => 'Fork',
          'hike' => 'Hike',
          'martini' => 'Martini',
          'monument' => 'Monument',
          'shopping' => 'Shopping',
          'winery' => 'Winery',
        ];
        
        foreach($arr as $key => $value) { ?>
            <option value="<?php echo esc_html($key) ?>" <?php echo ($icon == $key) ? 'selected' : '' ?>><?php echo esc_html($value) ?></option>
        <?php } ?>
        </select>
        <span class="components-input-control__suffix css-pvvbxf em5sgkm0">
          <div class="css-1j3xh4d e1mv6sxx0">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="18" height="18" role="img" aria-hidden="true" focusable="false"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>
          </div>
        </span>
        <div aria-hidden="true" class="components-input-control__backdrop css-29yhbg em5sgkm2"></div></div>
      </div>
  </div>
<?php
  }

  /**
   * Save the metadata information when the form is submitted
   * 
   * @return void
   */
  function unlimited_pins_save_postdata( $post_id ) {
    $prefix = 'unlimited_pins_field_';
    $save_items = array('caption', 'lng', 'lat', 'icon');

    foreach($save_items as $item) {
      if ( array_key_exists( $prefix.$item, $_POST ) ) {
          update_post_meta(
              $post_id,
              '_unlimited_pins_'.$item,
              $_POST[$prefix.$item]
          );
      }
    }
  }
	
  /**
   * Load sample data
   * 
   * @return void
   */
  function load_sample_data() {
    if( file_exists(__DIR__.'/import.lock') ) {
      return;
    }
    
    $pins = file_get_contents( __DIR__.'/import_pins.json');
    $pins = json_decode($pins);

    foreach($pins as $pin) {
      $lat = $pin[0];
      $lng = $pin[1];
      $caption = $pin[3];
      $url = $pin[4];

      if(!preg_match('{href="(.*)"}', $url, $matches)) {
        continue;
      }

      // Retrieve post id from url
      $post_id = url_to_postid($matches[1]);

      if($post_id < 1) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $matches[1]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);

        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);       
        $post_id = url_to_postid($url);

        if( $post_id < 1 ) {
          file_put_contents(__DIR__.'/not_found.txt', $url . PHP_EOL, FILE_APPEND | LOCK_EX);
          continue;
        }
      }
    
      $update = [
        '_unlimited_pins_caption' => $caption, 
        '_unlimited_pins_lat' => $lat, 
        '_unlimited_pins_lng' => $lng, 
        '_unlimited_pins_icon' => 'default'
      ];

      foreach($update as $key => $value) {
        update_post_meta(
          $post_id,
          $key,
          $value
        );
      }

      file_put_contents(__DIR__.'/import.lock', $matches[1] . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
  }


  /**
   * THIS METHOD SHOULD NEVER BE USED IN A PRODUCTION SETTING !
   */
  function load_data() {
    add_action('init', [$this, 'load_sample_data']);
  }
}

$plugin = new UnlimitedPins();

// Load our plugin within the WP admin dashboard.
if (is_admin()) {
  $plugin->load_admin();
 // $plugin->load_data();
}