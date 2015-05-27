<?php
/*
 * Plugin Name: Seller Quiz
 * Version: 1.0.7
 * Plugin URI: http://www.coldturkeygroup.com/
 * Description: Multiple choice quiz to help potential home owners decide whether or not they're ready to sell.
 * Author: Cold Turkey Group
 * Author URI: http://www.coldturkeygroup.com/
 * Requires at least: 4.0
 * Tested up to: 4.1
 *
 * @package Seller Quiz
 * @author Aaron Huisinga
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'SELLER_QUIZ_PLUGIN_PATH' ) )
	define( 'SELLER_QUIZ_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! defined( 'SELLER_QUIZ_PLUGIN_VERSION' ) )
	define( 'SELLER_QUIZ_PLUGIN_VERSION', '1.0.7' );

require_once( 'classes/class-seller-quiz.php' );

global $seller_quiz;
$seller_quiz = new ColdTurkey\SellerQuiz\SellerQuiz( __FILE__ );

if ( is_admin() ) {
	require_once( 'classes/class-seller-quiz-admin.php' );
	$seller_quiz_admin = new ColdTurkey\SellerQuiz\SellerQuiz_Admin( __FILE__ );
}
