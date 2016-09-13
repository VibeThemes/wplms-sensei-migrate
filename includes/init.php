<?php
/**
 * Initialization functions for WP SENSEI MIGRATION
 * @author      VibeThemes
 * @category    Admin
 * @package     Initialization
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPLMS_SENSEI_INIT{

    public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new WPLMS_SENSEI_INIT();

        return self::$instance;
    }

    private function __construct(){
    	if ( in_array( 'woothemes-sensei/woothemes-sensei.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || (function_exists('is_plugin_active') && is_plugin_active( 'woothemes-sensei/woothemes-sensei.php'))) {
			add_action( 'admin_notices',array($this,'migration_notice' ));
		}    	
    }
    function migration_notice(){

    }
}

WPLMS_SENSEI_INIT::init();