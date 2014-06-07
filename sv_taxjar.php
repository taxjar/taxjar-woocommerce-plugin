<?
class TaxJar {
    public $rates;

    public function __construct(){
        
        $this->id              = 'TarJar';
        $this->has_fields      = true;

        $this->init_form_fields();


        if ( is_admin() ){ // admin actions
          add_action( 'admin_menu', array($this, 'add_taxjar_menu' ));
          add_action( 'admin_init', array($this, 'admin_init' ));
        }

          add_action( 'woocommerce_calculate_totals', array($this, 'get_tax'), 0, 1);
          add_filter( 'woocommerce_cart_tax_totals',  array($this, 'get_taxes'), 0, 2);
          add_filter( 'woocommerce_order_tax_totals', array($this, 'get_taxes'), 0, 2);

    }

    public function add_taxjar_menu()
    {
        add_options_page(
            'TaxJar Plugin Settings', 
            'TaxJar Settings', 
            'manage_options', 
            'sv_taxjar_plugin', 
            array($this, 'plugin_settings_page')
        );
    }
    
    public function plugin_settings_page()
    {
        include(sprintf("%s/templates/settings.php", dirname(__FILE__))); 
    }


    /**
     * hook into WP's admin_init action hook
     */
    public function admin_init()
    {

        // add your settings section
        add_settings_section(
            'wp_plugin_template-section', 
            'TaxJar Settings', 
            array(&$this, 'settings_section_wp_plugin_template'), 
            'wp_plugin_template'
        );
        
        foreach($this->form_fields as $setting)
        {

            // register your plugin's settings
            register_setting('wp_plugin_template-group', $setting['title']);
            // add your setting's fields
            add_settings_field(
                $setting['title'], 
                $setting['description'],
                array(&$this, 'settings_field_input_'. $setting['type']), 
                'wp_plugin_template', 
                'wp_plugin_template-section',
                array(
                    'field' => $setting['title']
                )
            );
        }
    } // END public static function activate
    
    public function settings_section_wp_plugin_template()
    {
    }
    
    /**
     * This function provides text inputs for settings fields
     */
    public function settings_field_input_text($args)
    {
        // Get the field name from the $args array
        $field = $args['field'];
        // Get the value of this setting
        $value = get_option($field);
        // echo a proper input type="text"
        echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
    } // END public function settings_field_input_text($args)

    public function settings_field_input_checkbox($args)
    {
        // Get the field name from the $args array
        $field = $args['field'];
        // Get the value of this setting
        $value = get_option($field);
        
        // echo a proper input type="text"
        echo sprintf('<input type="checkbox" name="%s" id="%s" value="%s" %s />', $field, $field, '1', $value ?'checked=checked' : '' );
    } // END public function settings_field_input_text($args)
    
    public function init_form_fields()
    {
        $this->form_fields = array(
            array(
                'type'        => 'text',
                'title'       => __('TaxJar_Api_Key', 'woothemes'),
                'description' => __('Your TaxJar Api Key', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            array(
                'type'        => 'text',
                'title'       => __('TaxJar_Store_Addr', 'woothemes'),
                'description' => __('Your business address', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            array(
                'type'        => 'text',
                'title'       => __('TaxJar_Store_City', 'woothemes'),
                'description' => __('City', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            array(
                'type'        => 'text',
                'title'       => __('TaxJar_Store_State', 'woothemes'),
                'description' => __('State', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            array(
                'type'        => 'text',
                'title'       => __('TaxJar_Store_Zip', 'woothemes'),
                'description' => __('Zip', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            array(
                'type'        => 'checkbox',
                'title'       => __('Enable/Disable', 'woothemes'),
                'description'       => __('Enable TaxJar', 'woothemes'),
                'default'     => 'yes'
            ),
        );
    }

    public function get_taxes($taxes, $that)
    {
        $tax = new stdClass();
        $tax->rate = '0';
        $tax->tax_rate_id = 'Sales_Tax';
        $tax->label  = 'Sales Tax';
        $tax->shipping = 'yes';
        $tax->compound = 'no';
        $tax->calc_tax= 'per_order';
        $tax->formatted_amount = number_format(0,2);


         if($this->tax_total)
         {
              $tax->amount=   $this->tax_total;
              $tax->formatted_amount = number_format(intval($that->tax_total,2));
         } else {
              if (method_exists($that,'get_total_tax')) 
              {
                  $tax->amount =   $that->get_total_tax();
                  $tax->formatted_amount = number_format($that->get_total_tax(),2);
              }

         }

        return array(
              'TAX'=>     $tax
        );


    }

    public function get_tax($that)
    {

        global $woocommerce;
        session_start();
        $from_state = get_option('TaxJar_Store_State', true);
        $state    = $woocommerce->customer->get_state();

        if ($state != $from_state)
            return $that;

        $from_city  = get_option('TaxJar_Store_City');
        $from_zip   = get_option('TaxJar_Store_Zip');

        $postcode = $woocommerce->customer->get_postcode();
        $city     = $woocommerce->customer->get_city();

        $amount = $woocommerce->cart->subtotal;
        $shipping = $woocommerce->cart->shipping_total;

        $url = str_replace(' ', '%20', (sprintf('state=%s&amount=%s&shipping=%s&from_city=%s&from_zip=%s&to_city=%s&to_zip=%s',$state, $amount, $shipping, $from_city, $from_zip, $city, $postcode )));

        $locations = $_SESSION['locations_'. (string) md5($url)];
        if($locations)
        {
            error_log("Session");
            error_log($locations);
            $that->tax_total = $locations;
            $this->tax_total = $locations;
            return;
        }

        $ch = curl_init();
        $accesstoken = sprintf('token="%s"', get_option('TaxJar_Api_Key'));
        $header = array('Authorization: Token '.$accesstoken);
        curl_setopt($ch, CURLOPT_URL, 'api.taxjar.com/sales_tax?' . $url); 
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        $rest = curl_exec($ch);
        error_log( "Connection");
        curl_close($ch);

        $this->rates = json_decode($rest);
        $that->tax_total = $this->rates->amount_to_collect;
        $this->tax_total = $this->rates->amount_to_collect;
        $locations = $_SESSION['locations_'. (string) md5($url)] = $that->tax_total;
        
    }
    
}

new TaxJar();
