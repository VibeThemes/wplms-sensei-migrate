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
            add_action('wp_ajax_migration_woo_sensei_courses',array($this,'migration_woo_sensei_courses'));
            add_action('wp_ajax_migration_woo_sensei_course_to_wplms',array($this,'migration_woo_sensei_course_to_wplms'));
		}    	
    }
    function migration_notice(){
        $this->migration_status = get_option('wplms_sensei_migration');
        if(empty($this->migration_status)){
            ?>
            <div id="migration_sensei_courses" class="error notice ">
               <p id="sm_message"><?php printf( __('Migrate sensei courses to WPLMS %s Begin Migration Now %s', 'wplms-sm' ),'<a id="begin_wplms_sensei_migration" class="button primary">','</a>'); ?>
               </p>
               <?php wp_nonce_field('security','security'); ?>
                <style>.wplms_sm_progress .bar{-webkit-transition: width 0.5s ease-in-out;
    -moz-transition: width 1s ease-in-out;-o-transition: width 1s ease-in-out;transition: width 1s ease-in-out;}</style>
                <script>
                    jQuery(document).ready(function($){
                        $('#begin_wplms_sensei_migration').on('click',function(){
                            $.ajax({
                                type: "POST",
                                dataType: 'json',
                                url: ajaxurl,
                                data: { action: 'migration_woo_sensei_courses', 
                                          security: $('#security').val(),
                                        },
                                cache: false,
                                success: function (json) {

                                    $('#migration_sensei_courses').append('<div class="wplms_sm_progress" style="width:100%;margin-bottom:20px;height:10px;background:#fafafa;border-radius:10px;overflow:hidden;"><div class="bar" style="padding:0 1px;background:#37cc0f;height:100%;width:0;"></div></div>');

                                    var x = 0;
                                    var width = 100*1/json.length;
                                    var number = width;
                                    var loopArray = function(arr) {
                                        wpcw_ajaxcall(arr[x],function(){
                                            x++;
                                            if(x < arr.length) {
                                                loopArray(arr);   
                                            }
                                        }); 
                                    }
                                    
                                    // start 'loop'
                                    loopArray(json);

                                    function wpcw_ajaxcall(obj,callback) {
                                        
                                        $.ajax({
                                            type: "POST",
                                            dataType: 'json',
                                            url: ajaxurl,
                                            data: {
                                                action:'migration_woo_sensei_course_to_wplms', 
                                                security: $('#security').val(),
                                                id:obj.id,
                                            },
                                            cache: false,
                                            success: function (html) {
                                                number = number + width;
                                                $('.wplms_sm_progress .bar').css('width',number+'%');
                                                if(number >= 100){
                                                    $('#migration_sensei_courses').removeClass('error');
                                                    $('#migration_sensei_courses').addClass('updated');
                                                    $('#sm_message').html('<strong>'+x+' '+'<?php _e('Courses successfully migrated from Sensei to WPLMS','wplms-sm'); ?>'+'</strong>');
                                                }
                                            }
                                        });
                                        // do callback when ready
                                        callback();
                                    }
                                }
                            });
                        });
                    });
                </script>
            </div>
            <?php
        }
    }

    function migration_woo_sensei_courses(){
        if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'security') || !is_user_logged_in()){
            _e('Security check Failed. Contact Administrator.','vibe');
            die();
        }

        global $wpdb;
        $courses = $wpdb->get_results("SELECT id,post_title FROM {$wpdb->posts} where post_type='course'");
        $json=array();
        foreach($courses as $course){
            $json[]=array('id'=>$course->id,'title'=>$course->post_title);
        }
        //update_option('wplms_sensei_migration',1);
        
        $this->migrate_posts();

        print_r(json_encode($json));
        die();
    }

    function migration_woo_sensei_course_to_wplms(){
        if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'security') || !is_user_logged_in()){
            _e('Security check Failed. Contact Administrator.','vibe');
            die();
        }

        global $wpdb;
        $this->migrate_course_settings($_POST['id']);
    }

    function migrate_posts(){
        global $wpdb;
        $wpdb->query("UPDATE {$wpdb->posts} SET post_type = 'unit' WHERE post_type = 'lesson'");
    }

    function migrate_course_settings($course_id){
        //Course Settings
        // Course Curriclum- Unit connection (user status), Quiz connection - Question connection, Module connection
        // Pricing connection

    }
}

WPLMS_SENSEI_INIT::init();