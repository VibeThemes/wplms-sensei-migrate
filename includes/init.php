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
        $this->course_id = $_POST['id'];
        //Connect taxonomy
        $this->migrate_course_settings($_POST['id']);
        $this->migrate_course_curriculum($_POST['id']);

    }

    function migrate_posts(){
        global $wpdb;
        //Track all ids
        $wpdb->query("UPDATE {$wpdb->posts} SET post_type = 'unit' WHERE post_type = 'lesson'");
    }

    function migrate_course_settings($course_id){
        update_post_meta($course_id,'vibe_duration',9999);

        $pre_course = get_post_meta($course_id,'_course_prerequisite',true);
        if(!empty($pre_course)){
            update_post_meta($course_id,'vibe_pre_course',$pre_course);
        }

        $connected_product = get_post_meta($course_id,'_course_woocommerce_product',true);
        if(!empty($connected_product)){
            update_post_meta($course_id,'vibe_product',$connected_product);
        }

    }

    function migrate_course_curriculum($course_id){
        // Course Curriclum- Unit connection (user status), Quiz connection - Question connection, Module connection
        $this->curriculum=array();
        //1. Get all the connected modules
        $modules = wp_get_post_terms($course_id,'module');
        if(empty($modules) || is_wp_error($modules))
            return;

        $modules_with_order = array(); // array('2'=>'name','3'=>'name2');
        foreach($modules as $module){
            $modules_with_order[$module->term_id] = $module->name;
        }

        //Logic for sorting in custom order
        $module_order = get_post_meta($course_id,'_module_order',true); // array('3','2');
        if(!empty($module_order)){
            $temp = array();
            foreach (array_values($module_order) as $key) {
                $temp[$key] = $modules_with_order[$key] ;
            }
            $modules_with_order = $temp;
        }

        //With custom order
        foreach($modules_with_order as $module_id=>$module_name){
            $this->curriculum[]=$module_name;
            $this->get_module_units($module_id);
        }
    }

    function get_module_units($module_id){
        $args = array(
            'post_type'=>'unit',
            'posts_per_page'=>9999,
            'orderby'=>'meta_value_num',
            'order' => 'ASC',
            'meta_key'=>'_order_module_'.$module_id,
            'tax_query' => array(
                                array(
                                'taxonomy' => 'module',
                                'field' =>'term_id',
                                'terms' => $module_id,
                                )
                            )
            );

        $the_query = new WP_Query($args);
        if($the_query->have_posts()){
            while($the_query->have_posts()){
                $the_query->the_post();
                global $post;
                if($this->course_id == get_post_meta($post->ID,'_lesson_course',true)){
                    $this->curriculum[]=$post->ID;
                    //Migrate Unit settings
                    $this->migrate_unit_settings($post->ID);

                    $check_quiz = get_post_meta($post->ID,'_lesson_quiz',true);
                    //Quiz check.
                    if(!empty($check_quiz)){
                        if(get_post_type($check_quiz) == 'quiz'){
                            $this->curriculum[] = $check_quiz;
                            //Migrate quiz settings
                            $this->migrate_quiz_settings($check_quiz);
                            $this->migrate_quiz_questions($check_quiz);
                        }
                    }
                }
            }
        }
    }

    function migrate_unit_settings($unit_id){
        $unit_duration = get_post_meta($unit_id,'_lesson_length',true);
        if(!empty($unit_duration)){
            update_post_meta($unit_id,'vibe_duration',$unit_duration);
        }
    }

    function migrate_quiz_settings($quiz_id){
        update_post_meta($quiz_id,'vibe_quiz_course',$this->course_id);
        update_post_meta($quiz_id,'vibe_duration',9999);

        $quiz_pass = get_post_meta($quiz_id,'_pass_required',true);
        if(!empty($quiz_pass) && $quiz_pass == 'on'){
            $quiz_pass_marks = get_post_meta($quiz_id,'_quiz_passmark',true);
            if(!empty($quiz_pass_marks)){
                update_post_meta($quiz_id,'vibe_quiz_passing_score',$quiz_pass_marks);
            }
        }

        $auto_evaluate = get_post_meta($quiz_id,'_quiz_grade_type',true);
        if(!empty($auto_evaluate) && $auto_evaluate == 'auto'){
            update_post_meta($quiz_id,'vibe_quiz_auto_evaluate','S');
        }

        $quiz_retake = get_post_meta($quiz_id,'_enable_quiz_reset',true);
        if(!empty($quiz_retake) && $quiz_retake == 'on'){
            update_post_meta($quiz_id,'vibe_quiz_retakes',1);
        }

        $random_question = get_post_meta($quiz_id,'_random_question_order',true);
        if(!empty($random_question) && $random_question == 'yes'){
            update_post_meta($quiz_id,'vibe_quiz_random','S');
        }
    }

    function migrate_quiz_questions($quiz_id){
// Get type of question from question-type taxonomy.
        global $wpdb;
        $questions = $wpdb->get_results("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_quiz_id' AND meta_value = $quiz_id");

        $quiz_questions = array('ques'=>array(),'marks'=>array());
        if(!empty($questions)){
            foreach($questions as $question){
                $quiz_questions['ques'][] = $question->post_id;
                $question_marks = get_post_meta($question->post_id,'_question_grade',ture);
                if(!empty($question_marks)){
                    $quiz_questions['marks'][] = $question_marks;
                }
            }
            update_post_meta($quiz_id,'vibe_quiz_questions',$quiz_questions);
        }
    }
}

WPLMS_SENSEI_INIT::init();