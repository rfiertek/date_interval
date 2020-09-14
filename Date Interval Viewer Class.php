<?php
/**
* gsr_main_viewer_class.php
*
* $Revision: 1557 $
*
* $Date: 2014-04-24 09:03:12 -0500 (Thu, 24 Apr 2014) $
*
* $Id: gsr_main_viewer_class.php 1557 2014-04-24 14:03:12Z clemmtl $
*
* Capable of hiding viewer around the iframe by passing "gssmetrics" paramaters in the URL
*
* @package gsr_main_viewer_class
* @tutorial gsr_main_viewer_class.pkg
* @author Otmar Nytra
* @version $Revision: 1557 $  $Id: gsr_main_viewer_class.php 1557 2014-04-24 14:03:12Z clemmtl $
*
* // JJ 20130319 Corrected typos per Codetrack item id: 0029.
* // JJ 20130410 Added csv to the export options.
* // RF 20190304 Added features for Date Interval Widget
*/

  // include required classes
  require_once 'gsr_environment.php';
  //require_once 'gsr_gui_class.php';
  //require_once 'gsr_sqlsrv_db_class.php';
  require_once 'gsr_org_class.php';
  require_once 'instrument_select_class.php';
  require_once 'channel_select_class.php';
  require_once 'servicing_groups_select_class.php';
  require_once 'business_segments_select_class.php';
  require_once 'date_interval_select_class.php';

  //require_once 'assay_select_class.php';   // Since this is not common to ALL (or most) web apps this should be in the assay_viewer code. JJ 20110923


  /**
  * gsr_main_viewer_class
  *
  * $Revision: 1557 $
  *
  * $Date: 2014-04-24 09:03:12 -0500 (Thu, 24 Apr 2014) $
  *
  * Capable of hiding viewer around the iframe by passing "gssmetrics" paramaters in the URL
  *
  * This class is used to generate main viewer
  * @tutorial gsr_main_viewer_class.pkg
  * @package gsr_main_viewer_class
  * @author Otmar Nytra
  * @version $Revision: 1557 $  $Id: gsr_main_viewer_class.php 1557 2014-04-24 14:03:12Z clemmtl $ *
  */
  class gsr_main_viewer_class {

  /**
  * Array with export options selected
  *
  * @var array
  */
  protected $_export_options = array();
  /**
  * array containg top selectors HTML
  *
  * @var array
  */
  protected $top_selectors = array();
  /**
  * Array contains custom added js links
  *
  * @var mixed
  */
  protected $_custom_js_links = array();
  /**
  * Array containg left sections HTML
  *
  * @var array
  */
  protected $_left_menu_sections = array();
  /**
  * Array containing widths of the top button sizes (0 index is not used)
  *
  * @var mixed
  */
  protected $top_selectors_width = array(0,143,183);
  /**
  * table width based on the amount of the top selectors
  *
  * @var mixed
  */
  protected $top_selectors_table_width = 0;
  /**
  * Array containing custom javascript code inserted at the end of HTML
  *
  * @var array
  */
  protected $custom_js = array();

  /**
  * Array containing custom css links
  *
  * @var mixed
  */
  protected $_custom_css_link = array();

  /**
  * src of the iframe to load at the beginning
  *
  * @var string
  */
  protected $iframe_src = '';
  /**
  * Action of the main form
  *
  * @var string
  */
  protected $form_action = '#';
  /**
  * width of the left menu
  *
  * @var integer
  */
  protected $left_menu_width = '';
  /**
  * adjustrment of left menu content width used for overflow scroll bar
  *
  * @var mixed
  */
  protected $left_menu_section_width_adjust = 0;
  /**
  * Settings loaded from ini file
  *
  * @var array
  */
  protected $_gsr_main_viewer_class_ini;
  /**
  * gsr_gui_class
  *
  * @var gsr_gui
  */
  protected $gsr_gui_obj;
  /**
  * sqlsrv database object
  *
  * @var mixed
  */
  protected $db_obj;
  /**
  * Application name
  *
  * @var mixed
  */
  protected $_app_name = '';
  /**
  * Dosument type loaded from the ini file
  *
  * @var mixed
  */
  protected $html_doctype = '';

  /**
  * File name of the template file to load
  *
  * @var string File name of the template file to load
  */
  protected $_viewer_template_file = '';

  protected $_custom_bottom_html = array();

  /**
  * Clean template HTML with comments stripped
  *
  * @var string clean template HTML with comments stripped
  */
  protected $_viewer_template_html = '';
  /**
  * Indicates if autoload icon is displayed
  *
  * @var mixed
  */
  protected $_autoload_on = false;
  /**
  * Final generated HTML
  *
  * @var mixed
  */
  protected $_final_html = '';
  /**
  * Error status true/false
  *
  * @var boolean
  */
  protected $error_status = false;
  /**
  * Error message
  *
  * @var string
  */
  protected $error_message = false;
  /**
  * Main browser window title
  *
  * @var string
  */
  protected $_window_title = '';
  /**
  *
  */
  public $channel_type_left_side_html = '';
  /**
  *
  */
  public $servicing_groups_left_side_html = '';
  /**
  *
  */
  public $business_segments_left_side_html = '';

  /**
  * You can initiate class with template name passed in, if not it will use default one
  *
  * @param string $template_name template name to use
  * @return gsr_main_viewer_class
  */
  function __construct($template_name = ''){
    require_once 'gsr_main_viewer_ini.php';
    $this->_gsr_main_viewer_class_ini = $gsr_main_viewer_class_ini;
    // load template
    $this->_load_viewer_template($template_name);
    $this->gsr_gui_obj = new gsr_gui_class();
    $this->db_obj = new gsr_sqlsrv_db_class();
    if($this->db_obj->get_error_status()) $this->_set_error_status($this->db_obj->get_error_message());

    // set default export options
    $this->set_export_icons();

    //$this->InitializeTimeOutPlan(); // Comment this line out to turn off the time-out plan.
  }

  public function InitializeTimeOutPlan()
  {
    // Initializes a plan for dealing with the web page timing out because of federated security tokens expiring.
    // If the user is internal, refresh the page once an hour between 6 am and 8 pm.
    // If the user is external, show a message in an hour telling them they have been logged off.
    // The hour delay might actually be 5 or 10 minutes depending on where the user lives.

    // constants:
    $LOCAL_INTERNET_IP_PREFIX = "10.";
    $TIME_BUFFER_TO_PREVENT_SESSION_TIMEOUT = 120; // 2 minutes.
    $MILLISECONDS_TO_SECONDS = 1000;

    if ((isset($_SERVER['HTTP_REMOTE_NOT_ON_OR_AFTER'])) && (isset($_SERVER['HTTP_REMOTE_NOT_BEFORE'])) && (!isset($_REQUEST['disablerefresh'])))
    {
      $is_internal = isset($_SERVER['REMOTE_ADDR']) ? ($LOCAL_INTERNET_IP_PREFIX==substr($_SERVER['REMOTE_ADDR'],0,strlen($LOCAL_INTERNET_IP_PREFIX))) : false;
      $number_of_seconds_until_timeout = strtotime($_SERVER['HTTP_REMOTE_NOT_ON_OR_AFTER'])-strtotime($_SERVER['HTTP_REMOTE_NOT_BEFORE']);
      if (isset($_REQUEST['testrefresh']))
      {
        // This is for testing the timeout feature.
        $TIME_BUFFER_TO_PREVENT_SESSION_TIMEOUT = 60; // One minute.
        $number_of_seconds_until_timeout = $TIME_BUFFER_TO_PREVENT_SESSION_TIMEOUT + 20;
      }

      if ($is_internal) {
        $refresh_div = "<div id='divRefresh' class='top_csv_export_notification_div' style='display:none;z-index:5000;left:100px;posLeft:100'>";

        $refresh_div .= "<table border='0'><tr><td>Refreshing the web page so your session doesn't time out.</td>
                <td rowspan=2><img src='/gsr/common/images/progress_wheel.gif' id='imgRefreshProgress' style='display:none' width='30' /></td></tr>
                <tr><td>";
        $refresh_div .= $this->gsr_gui_obj->gen_glass_button('btnCancelRefresh','Cancel Refresh',150);
        $refresh_div .= "<span id='refreshCountDown'></span></td></tr></table></div>";
        $this->add_custom_bottom_html($refresh_div);

        $js = "DontLetSessionTimeout(".($number_of_seconds_until_timeout-$TIME_BUFFER_TO_PREVENT_SESSION_TIMEOUT).",".$TIME_BUFFER_TO_PREVENT_SESSION_TIMEOUT.");\n";
        $this->add_custom_js($js);
      } else
      {
        $js = "setTimeout(PageExpiredStartUserMessageToReAuthenticate,".($number_of_seconds_until_timeout*$MILLISECONDS_TO_SECONDS).");\n";
        $this->add_custom_js($js);
      }

      // Add a div to tell the user the page timed out.
      $timeout_div = "<div id='divPageTimingOut' class='gsr-top-page-timeout-div'>
      <table border=0><tr><td>The web page has timed out.</td>
      <td rowspan='2'><img src='/gsr/common/images/progress_wheel.gif' id='imgTimeoutRefreshProgress' style='display:none' width='30' /></td></tr>
      <tr><td>";
      $timeout_div .= $this->gsr_gui_obj->gen_glass_button("btnTimeoutRefresh","Refesh The Page",200);
      $timeout_div .= "</td></tr><tr><td>";
      $timeout_div .= $this->gsr_gui_obj->gen_glass_button("btnTimeoutClose","Close This Dialog",200);
      $timeout_div .= "</td></tr></table></div>";
      $this->add_custom_bottom_html($timeout_div);

      // Add javascript events for the buttons in the above html.
      $js = "AddTimeoutEvents()\n";
      $this->add_custom_js($js);
    }

    return;
  }

  /**
  * Set application name
  *
  * @param mixed $app_name
  */
  public function set_application_name($app_name){
    if($app_name !== '') $this->_app_name = $app_name;
  }

  /**
  * Set browser window title, if not used the application name will be used
  *
  * @param string $win_title
  */
  public function set_window_title($win_title){
    if($win_title !== '') $this->_window_title = $win_title;
  }

  /**
  * Adds custom javascript code to be included at the bottom of the page, the script is already enclosed on <script> tags just send code
  *
  * @param string $js
  */
  public function add_custom_js($js = ''){
    $this->custom_js[] = $js;
  }
  /**
  * Adds custom html code to the  bottom of the page <b>Outside of the main form</b>
  *
  * To be mostly used for hidden html elements, it is outside area that viewer class resizes
  *
  * @param string $html
  */
  public function add_custom_bottom_html($html){
    $this->_custom_bottom_html[] = $html;
  }

  // TODO: This method will be used to add custom js links
  public function add_custom_js_link($link){
    $this->_custom_js_links[] = $link;
  }

  /**
  * This method is used to add custom css links
  *
  * @param string $link css link to add
  */
  public function add_custom_css_link($link){
    $this->_custom_css_link[] = $link;
  }
  /**
  * Set the SRC or web link to the page initialy loaded in the iframe
  *
  * @param string $src
  */
  public function set_iframe_src($src = ''){
    $this->iframe_src = $src;
  }
  /**
  * Sets form action (target is the iframe)
  *
  * @param string $action
  */
  public function set_form_action($action = '#'){
    $this->form_action = $action;
  }
  /**
  * Override default left menu width (value in px)
  *
  * Use $overflow [true] when you expect your left menu sections to overflow the height of the screen (default false)
  *
  * @param integer $width
  * @param boolean $overflow true - make space for vertical scrollbar, false [default] - do not make the space
  */
  public function set_left_menu_width($width,$overflow = false){
    $this->left_menu_width = $width;
    $this->left_menu_section_width_adjust = 18;
    if($overflow) $this->left_menu_section_width_adjust = 35;
  }
  /**
  * Turns on the loading image on the top when page is loading
  *
  * You have to add this hidden input to the bottom of the page for it to know when page is loaded:
  *
  * <input type="hidden" value="1" id="page_loaded">
  *
  *
  */
  public function set_load_indicator_on(){
    if(!$this->_autoload_on){
      $html = '<div id="loading" style="display:hidden;position:absolute;left:300px;top:20px;"><img src="/gsr/common/images/ajax-loader.gif" alt=""></div>';
      $this->add_custom_bottom_html($html);
      $js = '
      autoload_on = true;
      setTimeout("check_loading()",1000);
      var counter = 0;
      function check_loading(){
        $("#loading").show();
        var find_id = $("#data_iframe").contents().find("#page_loaded").val();
        counter++;
        html = $("#loading").html();
        // $("#loading").html(counter + ":" + find_id);
        if(find_id == "1") {$("#loading").hide();}
        setTimeout("check_loading()",1000);
      };
      ';
      $this->add_custom_js($js);
      $this->_autoload_on = true;
    }


  }

  /**
  * Sets the HTML template name, if not exists sets the error message
  *
  * @param string $template_name
  */
  public function set_html_template($template_name = ''){
    $this->_clear_error_status();
    $templates = $this->_gsr_main_viewer_class_ini['templates'];
    if($template_name !== '') {
      if(array_search($template_name,$templates) !== false) $this->_viewer_template_file = $template_name;
      else {
        // load default and set error
        $this->_set_error_status('Template name "'.$template_name.'" doesn\'t exists, using default one');
        $this->_viewer_template_file = $templates[0];
      }
    }
    else $this->_viewer_template_file = $templates[0];
  }

  /**
  * Loads the default template file and strips the top PHP code
  *
  * If error occurs sets the error status and message
  * @param string $template_name name of template to use, if not passed selects first template
  * @uses _set_error_status
  * @uses set_html_template
  */
  protected function _load_viewer_template($template_name = ''){
    $this->_clear_error_status();
    // load template name
    $this->set_html_template($template_name);

   // load file if exists
   if(file_exists($this->_viewer_template_file)) {
      $this->_viewer_template_html = file_get_contents($this->_viewer_template_file);
      if($this->_viewer_template_html === false) {$this->_set_error_status('Unable to load template file ['.$this->_viewer_template_file.']!');}
      else {
        // strip php from template
        $start = strripos($this->_viewer_template_html,'?>') + 2;
        $this->_viewer_template_html = substr($this->_viewer_template_html,$start);
      }
   }
   else $this->_set_error_status('Unable to load template file ['.$this->_viewer_template_file.']!');
  }

  /**
  * Sests error status to true and error message
  *
  * @param string $msg error message to be set
  * @uses error_status
  * @uses error_message
  */
  protected function _set_error_status($msg){
    $this->error_status = true;
    $this->error_message = $msg;
  }

  /**
  * Clears error status (to false) and error messsage to ''
  *
  * @uses error_status
  * @uses error_message
  */
  protected function _clear_error_status(){
    $this->error_status = false;
    $this->error_message = '';
  }
  /**
  * Error status true/false = yes/no
  *
  * @return boolean
  */
  public function get_error_status(){
    return $this->error_status;
  }
  /**
  * Returns error message if any
  *
  * @return string empty = no error
  */
  public function get_error_message(){
    return $this->error_message;
  }
  /**
  * Add top navigation selector
  *
  * generates button on the top and corresponding div box to show
  *
  * function $("#id").click(): show or hides the div_box
  *
  * @param integer $size 1 - smaller button, 2 - bigger button
  * @param mixed $id div button and div box id (box id will be id_div_box)
  * @param string $title Box title
  * @param string $div_box_html html inside box
  * @param integer $div_box_width width of the box in px
  * @param boolean $close_button add or not close button on the bottom of the box
  */
  public function add_top_navigation_selector($size = 1,$id = '',$title = '',$div_box_html,$div_box_width,$close_button = false,$submit_button = false){

  $index = 0;
  if($id !== '' && $title !== ''){
    switch ($size) {
      case 1:
        $this->top_selectors[]['html'] = "<td class=\"gsr_top_selector\" id=\"".$id."\">".$title."<br><span></span></td>";
        $index = count($this->top_selectors)-1;
        $this->top_selectors[$index]['id'] = $id;
        $this->top_selectors[$index]['size'] = $size;
        break;
      case 2:
        $this->top_selectors[]['html'] = "<td class=\"gsr_top_selector_183\" id=\"".$id."\">".$title."<br><span></span></td>";
        $index = count($this->top_selectors)-1;
        $this->top_selectors[$index]['id'] = $id;
        $this->top_selectors[$index]['size'] = $size;
        break;
    }

    if($close_button) $div_box_html .= "<br><center>".$this->gsr_gui_obj->gen_glass_button($id.'_div_box_close_button','CLOSE',100)."</center>";
    $params = array(
            'div_id' => $id.'_div_box',
            'title' => $title,
            'width' => $div_box_width,
            'hidden' => true,
            'close_in_title' => true,
            'html' => $div_box_html
            );
    if($submit_button) {
      $div_box_html .= "<br><center>".$this->gsr_gui_obj->gen_glass_button($id.'_div_box_submit_button','SUBMIT',100)."</center>";
      $this->add_custom_js('
      $("#'.$id.'_div_box_submit_button").click(function(){
        DoSubmission(true);
      });
      ');
    }
    $params = array(
            'div_id' => $id.'_div_box',
            'title' => $title,
            'width' => $div_box_width,
            'hidden' => true,
            'close_in_title' => true,
            'html' => $div_box_html
            );
    $this->top_selectors[$index]['div_box'] = $this->gsr_gui_obj->div_box($params);
    $this->top_selectors_table_width += $this->top_selectors_width[$this->top_selectors[$index]['size']]+10;
  }
  }

  protected function _get_top_selectors_table_width(){
    return $this->top_selectors_table_width;
  }

  protected function _get_top_navigation_selectors_code(){
  $ret = '';
  // check if selectors are defined
  if(count($this->top_selectors) == 0) return NULL;
  foreach($this->top_selectors as $selector) $ret .= $selector['html'];
  return $ret;
  }

  protected function _get_top_navigation_selectors_div_boxes(){
  $ret = '';
  // check if selectors are defined
  if(count($this->top_selectors) == 0) return NULL;
  foreach($this->top_selectors as $selector) $ret .= $selector['div_box'];
  return $ret;
  }
  /**
  * generates Javascript for all top navigation selectors
  *
  */
  protected function _get_top_navigation_selectors_js(){
    $ret = 'var selectors_array = new Array();';
    $i=0;
    foreach($this->top_selectors as $selector) {
      $id = "'#".$selector['id']."'";
      $div_id = "'#".$selector['id']."_div_box'";
      $div_id_close = "'#".$selector['id']."_div_box_title_close'";
      $div_id_close_button = "'#".$selector['id']."_div_box_close_button'";
      $ret .= "selectors_array[$i] = $div_id;";
      $i++;
      $ret .= "
        \$(".$id.").mouseover(function() {

          \$(".$id.").removeClass('gsr-top-sel-bgr".$selector['size']."');
          \$(".$id.").addClass('gsr-top-sel-bgr-hover".$selector['size']."');
        });
        \$(".$id.").mouseout(function() {

          \$(".$id.").removeClass('gsr-top-sel-bgr-hover".$selector['size']."');
          \$(".$id.").addClass('gsr-top-sel-bgr".$selector['size']."');

        });
        ";
      $ret .= "
          \$(".$id.").click(function() {
          hide_other_selectors($div_id);
          pos = get_selector_position(".$id.");

          \$(".$div_id.").css('left',pos[0]);
          \$(".$div_id.").css('top',pos[1]);
          \$(".$div_id.").toggle();
          });
          \$($div_id_close).click(function(){
            \$($id).click();
          });
          \$($div_id_close_button).click(function(){
            \$($id).click();
          });
        ";
    }
    return $ret;
  }

  /**
  * GSR Top Menu Generator
  *
  * @param mixed $gsr_menu_items
  * @param mixed $environment Default ALL avaliable options: ALL,DEV,TEST,PROD which items will be displayed
  */
  protected function _gsr_top_menu( $menu_table_name , $environment = 'ALL'){

      switch ($environment){
        case 'DEV':
               $env_sql = 'dev_active = 1';
               $link_sql = 'dev_link AS link';
               break;
        case 'TEST':
               $env_sql = 'test_active = 1';
               $link_sql = 'test_link AS link';
               break;
        case 'QA':
               $env_sql = 'qa_active = 1';
               $link_sql = 'qa_link AS link';
               break;
        case 'PROD':
               $env_sql = 'prod_active = 1';
               $link_sql = 'prod_link AS link';
               break;


      }
      // read menu items from the DB
      $db_obj = new sqlsrv_db_class();
      $db_obj->run_sql_save("SELECT
                   [L1]
                  ,[L2]
                  ,[web_app_icon]
                  ,[title]
                  ,[validation_icon]
                  ,[validated_for_prod]
                  ,".$link_sql."
                  ,[target]
                  ,[description]
                  ,[submit_request]
                  FROM $menu_table_name
                  WHERE ".$env_sql."
                  ORDER BY [L1],[L2]");
      $menu_array = $db_obj->fetch_array_assoc();

      // prepare menu array
      $l1 = 0;$gsr_menu_items = array();
      foreach($menu_array as $item){
        if($item['L1'] == 0 AND $item['L2'] == 0) {
          $gsr_menu_items['main_title'] = $item['title'];
          continue;
        }
        if($item['L1'] != $l1) $l1 = $item['L1'];

        if($item['L2'] == 0) {
          $gsr_menu_items['items'][$l1]['title'] = $item['title'];
          continue;
        }
        $gsr_menu_items['items'][$l1]['items'][$item['L2']]['title'] = $item['title'];
        $gsr_menu_items['items'][$l1]['items'][$item['L2']]['link'] = $item['link'];
        $gsr_menu_items['items'][$l1]['items'][$item['L2']]['desc'] = $item['description'];
        $gsr_menu_items['items'][$l1]['items'][$item['L2']]['validation_icon'] = $item['validation_icon'];
        $gsr_menu_items['items'][$l1]['items'][$item['L2']]['validated_for_prod'] = $item['validated_for_prod'];
      }

    $end_line = "\r\n";
    $gsr_menu_html = ''.$end_line;
    $gsr_menu_html .= '<div id="menu">'.$end_line;
    $gsr_menu_html .= '<ul class="sf-menu">'.$end_line;
    $gsr_menu_html .= '<li>'.$end_line;

    $gsr_menu_html .= '<a href="#a">'.$gsr_menu_items['main_title'].'</a>'.$end_line;
    $gsr_menu_html .= '<ul>'.$end_line;
    foreach ($gsr_menu_items['items'] as $menu_group){
      $gsr_menu_html .= '<li>'.$end_line;
      $gsr_menu_html .= '<a href="#a">'.$menu_group['title'].'</a>'.$end_line;
        $gsr_menu_html .= '<ul>'.$end_line;
        foreach ($menu_group['items'] as $menu_level1){
          if($menu_level1["validated_for_prod"] == "Y") {
          $validated_icon = '<div class="sf-menu-validated-icon">';
           $validated_icon .= $menu_level1["validation_icon"];
           $validated_icon .= "</div>";
          }
          else {
            $validated_icon = "";
          }
          $gsr_menu_html .= '<li>'.$end_line;
          if($menu_level1["link"] !== NULL && $menu_level1["link"] !== "") $on_click = '"javascript:menu_submit(\''.$menu_level1["link"].'\')"';
          else $on_click = '"#"';

          $gsr_menu_html .= '<a href='.$on_click.'  title="'.htmlentities($menu_level1['desc'],ENT_QUOTES).'"  target="_top" >'.htmlentities($menu_level1['title'],ENT_QUOTES).'</a>'.$validated_icon.$end_line;
          $gsr_menu_html .= '</li>'.$end_line;
        }
        $gsr_menu_html .= '</ul>'.$end_line;
        $gsr_menu_html .= '</li>'.$end_line;

    }
    $gsr_menu_html .= '</ul>'.$end_line;
    $gsr_menu_html .= '</li>'.$end_line;
    $gsr_menu_html .= '</ul>'.$end_line;
    //$gsr_menu_html .= '</li>'.$end_line;  // JJ extra lines per HTML Validator Pro 20110923.
    //$gsr_menu_html .= '</ul>'.$end_line;
    $gsr_menu_html .= '</div>'.$end_line;
    unset($db_obj);
    return $gsr_menu_html;
  }

  protected function _get_left_menu_sections_html(){
    $ret = '';
    foreach($this->_left_menu_sections as $section) $ret .= $section;
    return $ret;
  }

  /**
  * Adds left section to the left menu
  *
  * You don't have to pass all keys, just the ones you need or feel like.
  * - 'id': id to use (you should use this one)
  * - 'title': title of the section, if not used no title will be displayed
  * - 'html': html to use inside section (this might be helpfull to pass otherwise nothing will be displayed)
  * - 'border': true/false - display border around section [default true]
  * - 'padded' [DEFAULT] true: adds padding to the top and bottom of the section, false: no padding
  *
  * @param array $params parameters
  */
  public function add_left_menu_section($params = array('id'=>'','title'=>'','html'=>'','padded' => true)){

    if(!is_array($params)) return 'Invalid Parameters add_left_menu_section!';
    if(!array_key_exists('id',$params)) $params['id'] = $this->gsr_gui_obj->get_unique_id();
    if(!array_key_exists('title',$params)) $params['title'] = '';
    if(!array_key_exists('html',$params)) $params['html'] = '';
    if(!array_key_exists('border',$params)) $params['border'] = true;
    if(!array_key_exists('padded',$params)) $params['padded'] = true;

    if($params['padded'] === true){
      $params['html'] = '<div class="left_section_html_padded">'.$params['html'].'</div>';
    }

    $class = 'left_menu_section';
    if(!$params['border']) $class = 'left_menu_section_noborder';
    // stop if nothing passed in
    if($params['html'] === '') return false;

    $width = $this->left_menu_width - $this->left_menu_section_width_adjust;

    $html = '
      <div id="'.$params['id'].'" class="'.$class.'" style="width: '.$width.'px">
        ';

    if($params['title'] !== '') $html .= '<div class="left_menu_section_title" style="width: '.$width.'px">'.$params['title'].'</div>';
    $html .= $params['html'].'</div>
    ';
    $this->_left_menu_sections[] = $html;
  }

  /**
   * get left section html
   *
   * You don't have to pass all keys, just the ones you need or feel like.
   * - 'id': id to use (you should use this one)
   * - 'title': title of the section, if not used no title will be displayed
   * - 'html': html to use inside section (this might be helpfull to pass otherwise nothing will be displayed)
   * - 'border': true/false - display border around section [default true]
   * - 'padded' [DEFAULT] true: adds padding to the top and bottom of the section, false: no padding
   *
   * @param array $params parameters
   * @return string $html generated html string
   */
  public function get_left_menu_section($params = array('id'=>'','title'=>'','html'=>'','padded' => true)){

    if(!is_array($params)) return 'Invalid Parameters add_left_menu_section!';
    if(!array_key_exists('id',$params)) $params['id'] = $this->gsr_gui_obj->get_unique_id();
    if(!array_key_exists('title',$params)) $params['title'] = '';
    if(!array_key_exists('html',$params)) $params['html'] = '';
    if(!array_key_exists('border',$params)) $params['border'] = true;
    if(!array_key_exists('padded',$params)) $params['padded'] = true;

    if($params['padded'] === true){
      $params['html'] = '<div class="left_section_html_padded">'.$params['html'].'</div>';
    }

    $class = 'left_menu_section';
    if(!$params['border']) $class = 'left_menu_section_noborder';
    // stop if nothing passed in
    if($params['html'] === '') return false;

    $width = $this->left_menu_width - $this->left_menu_section_width_adjust;

    $html = '
    <div id="'.$params['id'].'" class="'.$class.'" style="width: '.$width.'px">
    ';

    if($params['title'] !== '') $html .= '<div class="left_menu_section_title" style="width: '.$width.'px">'.$params['title'].'</div>';
    $html .= $params['html'].'</div>
    ';

    return $html;
  }

  /**
  * Adds Organizaions selector on the top menu
  *
  *
  * @param array $server_parms_in   (typically send in $_REQUEST) it contains the parameters for org and Others [DEFAULT] $_REQUEST
  * @param bool $include_locallevel   boolean true (default) means include Local Levels
  * @param bool $include_country    boolean true (default) means include Country
  * @param int  $org_choice       indicator of what org choice we want: 0=no org, 1=org (default), 2=area_with_WW, 3=area_no_WW.
  * @param int  $monthsback_locallevel  integer of how many months back to use to gather the locallevels 0=current only, 9999 (inclusive) (default).
  */
  public function add_top_navigation_orgs(
                 $server_parms_in = false,
                 $include_locallevel = true,
                 $include_country = true,
                 $org_choice = 1,
                 $monthsback_locallevel = false,
                 $std_country_location_label = false){
    if($monthsback_locallevel === false) $monthsback_locallevel = 9999;
    if($server_parms_in === false) $server_parms_in = $_REQUEST;

    $org_obj = new gsr_org_class($this->db_obj, $_REQUEST, $include_locallevel, $include_country, $org_choice, $monthsback_locallevel );
    if ($std_country_location_label) {
      $org_obj->set_std_country_location_label();
    }

    $inst_html = $org_obj->get_div_viewer_output();
    $inst_html .= '<div class="gui_clear_both"></div>' . $this->gsr_gui_obj->gen_glass_button('orgs_submit',
                    'SUBMIT',
                      110);
    $box_width = 0;

    if($include_locallevel === true) $box_width += 160;
    if($include_country === true) $box_width += 160;
    if($org_choice > 1) $box_width += 180;
    if($org_choice == 1) $box_width += 180;
    $this->add_top_navigation_selector(2,'top_sel_orgs','Organizations',$inst_html,$box_width,false);
    unset($org_obj);
    $js = '$("#orgs_submit").click(function(){
              $("#top_sel_orgs").click();
              DoSubmission(true);
            });';
    $this->add_custom_js($js);
  }

  /**
  * Add Instrument Selector to the top Menu
  *
  * <b>Parameters array structure:</b><br>
  * - 'special_sql_filename': PHP file that contains the special instrument sql (SQL must be contained in variable 'sql') (Optional)
  * - 'request': The $_REQUEST to allow for preselection of items. (default - none)
  * - 'incl_legacy_checkbox': boolean default is false
  * - 'incl_pl_group_select': Set to true to add a instrument group drop down box (Default - false)
  * - 'multi_select': Set to true is instrument selection should be multi-select (Default - false)
  *
  * @param array $params
  */
  public function add_top_navigation_instruments($parameters = array()){
    $parameters['db_obj'] = $this->db_obj;
    $box_size = 200;
    if(isset($parameters['incl_pl_group']) && $parameters['incl_pl_group'] === true) $box_size = 400;
    $inst_select_obj = new instrument_select_class($parameters);
    $inst_html = $inst_select_obj->gen_instrument_select_html();
    $inst_hidden_selects = $inst_select_obj->gen_plgroup_select_html();
    $inst_html .= '<div class="gui_clear_both"></div><center><br>' . $this->gsr_gui_obj->gen_glass_button('inst_submit',
                    'SUBMIT',
                      110).'</center>';
    $this->add_top_navigation_selector(1,'instruments','Instruments',$inst_html,$box_size,false);
    $inst_js = $inst_select_obj->gen_instrument_js();
    $this->add_custom_js($inst_js);
    $this->add_custom_bottom_html($inst_hidden_selects);
  }
  /**
  * Add Channel Type Selector to the top Menu
  *
  * <b>Parameters array structure:</b><br>
  * - 'request': The $_REQUEST to allow for preselection of items. (default - none)
  * - 'multi_select': Set to true if selection should be multi-select (Default - false)
  *
  * @param array $params
  */
  public function add_top_navigation_channel_type($parameters = array()){
    $parameters['db_obj'] = $this->db_obj;
    if(!isset($parameters['top_widget'])){
      $top_widget = True;
    } else {
      $top_widget = $parameters['top_widget'];
    }
    $box_size = 200;
    $channel_select_obj = new channel_select_class($parameters);
    $channel_type_html = $channel_select_obj->gen_channel_type_select_html($top_widget);
    $this->channel_type_left_side_html =  $channel_type_html;
    $this->add_custom_js_link("/gsr/common/javascript/jquery.jCombo.js");
    $channel_type_js = $channel_select_obj->gen_channel_type_js();
    $this->add_custom_js($channel_type_js);
    if($top_widget){
      $channel_type_html .= '<div class="gui_clear_both"></div><center><br>' . $this->gsr_gui_obj->gen_glass_button('channel_type_submit',
                      'SUBMIT',
                        110).'</center>';
      $this->add_top_navigation_selector(1,'channel_type_widget','Channel Type',$channel_type_html,$box_size,false);
      //$channel_type_js = $channel_select_obj->gen_channel_type_js();
       // $this->add_custom_js($channel_type_js);
    }
  }
  /**
  * put your comment there...
  *
  */
  public function get_channel_type_left_side_html () {
    $this->add_top_navigation_channel_type(array('top_widget'=>False));
    return  $this->channel_type_left_side_html;
  }

  /**
  * Add Servicing Groups Selector to the top Menu
  *
  * <b>Parameters array structure:</b><br>
  * - 'request': The $_REQUEST to allow for preselection of items. (default - none)
  *
  * @param array $params
  */

  public function add_top_navigation_servicing_groups($parameters = array()){
    $parameters['db_obj'] = $this->db_obj;
    if(!isset($parameters['top_widget'])){
      $top_widget = true;
    } else {
      $top_widget = $parameters['top_widget'];
    }
    if(!isset($parameters['request'])){
      $parameters['request'] = $_REQUEST;
    }

    $sg_parm = (!isset($parameters['sg_parm_name'])) ? 'sg' : $parameters['sg_parm_name'];  // Allows override to sgp (SG Phone) or sgs (SG site).
    // Other direct parameter values that can get sent in.
    // limit_to_active_sg_only

    $box_size = 330;
    $sg_obj = new servicing_groups_select_class($parameters);
    $sg_html = $sg_obj->gen_sg_select_html($top_widget, $sg_parm);
    $this->servicing_groups_left_side_html = $sg_html;
    $this->add_custom_js($sg_obj->gen_sg_js() );
    if($top_widget){
      $sg_html .= '<div class="gui_clear_both"></div><center><br>' . $this->gsr_gui_obj->gen_glass_button('sg_submit',
                      'SUBMIT',
                        110).'</center>';
      $this->add_top_navigation_selector(1,'sg_widget','Servicing Groups',$sg_html,$box_size,false);
    }
  }
  /**
  * put your comment there...
  *
  */
  public function get_servicing_groups_left_side_html($parameters = array() ) {
    $parameters['top_widget'] = false;
    $this->add_top_navigation_servicing_groups($parameters);
    return  $this->servicing_groups_left_side_html;
  }




  public function add_top_navigation_business_segments($parameters = array()){
    $parameters['db_obj'] = $this->db_obj;
    if(!isset($parameters['top_widget'])){
      $top_widget = true;
    } else {
      $top_widget = $parameters['top_widget'];
    }
    if(!isset($parameters['request'])){
      $parameters['request'] = $_REQUEST;
    }

    $box_size = 200;
    $bs_obj = new business_segments_select_class($parameters);
    $bs_html = $bs_obj->gen_bs_select_html($top_widget);
    $this->business_segments_left_side_html = $bs_html;
    $this->add_custom_js($bs_obj->gen_bs_js() );
    if($top_widget){
      $bs_html .= '<div class="gui_clear_both"></div><center><br>' . $this->gsr_gui_obj->gen_glass_button('bs_submit',
                      'SUBMIT',
                        110).'</center>';
      $this->add_top_navigation_selector(1,'bs_widget','Business Segments',$bs_html,$box_size,false);
    }
  }
  /**
  * put your comment there...
  *
  */
  public function get_business_segments_left_side_html($parameters = array() ) {
    $parameters['top_widget'] = false;
    $this->add_top_navigation_business_segments($parameters);
    return  $this->business_segments_left_side_html;
  }





  /**
  * Add Assay Selector to the top menu
  *
  * For testing purposes
  *
  */

    public function add_top_navigation_assays($parameters = array()){
    $parameters['db_obj'] = $this->db_obj;
    $box_size = 200;
    if(isset($parameters['incl_pl_group']) && $parameters['incl_pl_group'] === true) $box_size = 400;
    $inst_select_obj = new assay_select_class($parameters);
    $inst_html = $inst_select_obj->gen_instrument_select_html();
    $inst_hidden_selects = $inst_select_obj->gen_plgroup_select_html();
    $inst_html .= '<div class="gui_clear_both"></div><center><br>' . $this->gsr_gui_obj->gen_glass_button('inst_submit',
                    'SUBMIT',
                      110).'</center>';
    $this->add_top_navigation_selector(1,'instruments','Instruments',$inst_html,$box_size,false);
    $inst_js = $inst_select_obj->gen_instrument_js();
    $this->add_custom_js($inst_js);
    $this->add_custom_bottom_html($inst_hidden_selects);
  }

  /**
  * Adds dates Selector to the top menu
  *
  * None of the paramaters array keys are required, if none passed it will automatically select 10 years back till today
  *
  * Note: You can pass array with just one or more keys and other keys will be automatically set to default values
  *
  * It will set end date selected to current month if that parameter is not set
  *
  * @param array $parameters min_date/max_date: min/max dates to be displayed, start_set/end_set: set start/end selected date, start_id/end_id: id's for start/end date selectors (all date formats are YYYYMM)
  * @return mixed
  */
  public function add_top_navigation_dates($parameters = array('min_date'=>'','max_date'=>'','start_set'=>'','end_set'=> '','start_id'=>'minperiod','end_id'=>'maxperiod')){
    if(!is_array($parameters)) return 'Invalid Parameters add_top_navigation_dates';
    if(!array_key_exists('start_id',$parameters)) $parameters['start_id'] = 'minperiod';
    if(!array_key_exists('end_id',$parameters)) $parameters['end_id'] = 'maxperiod';
    if(!array_key_exists('min_date',$parameters)) $parameters['min_date'] = '';
    if(!array_key_exists('max_date',$parameters)) $parameters['max_date'] = '';


    $parameters['class'] = 'top_';
    $parameters['id'] = $parameters['start_id'];
    // check for values from parms
    if(array_key_exists('start_set',$parameters)) $parameters['selected_value'] = $parameters['start_set'];
    // check for values from request

    $html = '<div id="top_sel_dates_select">
          <span>Start Date:</span><br>
          '.$this->gsr_gui_obj->gen_date_range_form_select($parameters).'
          <br>';


    $parameters['id'] = $parameters['end_id'];
    if(array_key_exists('end_set',$parameters)) $parameters['selected_value'] = $parameters['end_set'];

    // TODO: Check for valid date selections
    $html .= '<span>End Date:</span><br>
          '.$this->gsr_gui_obj->gen_date_range_form_select($parameters).'<br><span id="top_sel_dates_div_box_error" class="top_div_box_error_msg"></span>
           '.$this->gsr_gui_obj->gen_glass_button('top_dates_selector_submit','SUBMIT',100).'
            </div>';
    $js = '
      $("#'.$parameters['start_id'].'").change(function(){
        start_val = $("#'.$parameters['start_id'].' :selected").val();
        end_val = $("#'.$parameters['end_id'].' :selected").val();

        check_valid_date_range(start_val,end_val,"'.'#'.$parameters['start_id'].'","'.'#'.$parameters['end_id'].'","#top_sel_dates_div_box_error");
        start = $("#'.$parameters['start_id'].' :selected").text();
        end = $("#'.$parameters['end_id'].' :selected").text();
        date_text = start + "";
        date_text += " -> " + end + "";
        $("#top_sel_dates span").html(date_text);

      });
      $("#'.$parameters['end_id'].'").change(function(){
        start_val = $("#'.$parameters['start_id'].' :selected").val();
        end_val = $("#'.$parameters['end_id'].' :selected").val();

        check_valid_date_range(start_val,end_val,"'.'#'.$parameters['start_id'].'","'.'#'.$parameters['end_id'].'","#top_sel_dates_div_box_error");
        start = $("#'.$parameters['start_id'].' :selected").text();
        end = $("#'.$parameters['end_id'].' :selected").text();
        date_text = start + "";
        date_text += " -> " + end + "";
        $("#top_sel_dates span").html(date_text);
      });

      $("#top_dates_selector_submit").click(function(){
        $("#top_sel_dates").click();
        DoSubmission(true);
      });

      function check_valid_date_range(start,end,s_id,e_id,box_id){
        s_year = start.substring(0,4);
        e_year = end.substring(0,4);
        s_m = start.substring(4,6);
        e_m = end.substring(4,6);
        msg = "";
        if(e_year < s_year) {
          $(e_id).val(start);
          msg = "End Date must be later<br>than Start Date";
        }
        if( (e_year == s_year) &&(s_m > e_m) ) {
          $(e_id).val(start);
          msg = "End Date must be later<br>than Start Date";
        }
        $(box_id).html(msg);
      }

      $("#'.$parameters['start_id'].'").change();
    ';
    $this->add_top_navigation_selector(1,'top_sel_dates','Dates',$html,150,false);
    $this->add_custom_js($js);
  }

  /**
  * Add Date Interval Selector to the top Menu
  *
  * <b>Parameters array structure:</b><br>
  * - 'special_sql_filename': PHP file that contains the special date interval sql (SQL must be contained in variable 'sql') (Optional)
  * - 'request': The $_REQUEST to allow for preselection of items. (default - none)
  *
  * None of the paramaters array keys are required, if none passed it will automatically select date display range of 201701 till today
  *
  * Note: You can pass array with just one or more keys and other keys will be automatically set to default values
  *
  * It will set end date (di_last) displayed to current day if that parameter is not set
  *
  * di_min and di_max formatting:
  * Year: 2018
  * Quarter: 2018q1 (note: quarter number is to have no leading zero)
  * Month: 201801
  * Week: 2018w01 (note: week numbers below 10 are to include a leading zero)
  * Day: 2018-01-01 (y,m,d. including leading zeroes where single digits)
  *
  * di_first and di_last should be set to 'day' format: 2018-01-01 (y,m,d)
  *
  * 'Week' standard is derived from PHP date() function, which adheres to ISO standards. this is important when trying to dertemine whether a week begins on Sunday or Monday, and
  *   on week counts around the turn of the year, when in some years, a year will have a 53rd week that rolls over into the next year (Dec. 28 in any year will always be part of any
  *   week that is completely within the existing year (Dec 29 may be part of week 1 of the coming year)) -- when parsing week data, try to use the PHP date function for total consistency
  *
  * @param array $parameters
  *   di_min/di_max: set start/end selected date (default: 201801 to 201812),
  *   di_first/di_last: set date range to be displayed,
  *   di_int: date selection interval (y/q/m/w/d/000: year,quarter,month,week,day,all. default: '000'),
  *   di_display: date interval to default to on initial display (default: 'm')
  * @return mixed

  */
  public function add_top_navigation_date_interval($parameters = array()){
    $parameters['db_obj'] = $this->db_obj;
    $box_size = 225;
    $di_select_obj = new date_interval_select_class($parameters);
    $di_html = $di_select_obj->gen_date_interval_select_html();
    $di_html .= '<div class="gui_clear_both"></div><center><br>' . $this->gsr_gui_obj->gen_glass_button('di_submit',
                    'SUBMIT',
                      110).'</center>';
    $this->add_top_navigation_selector(1,'date_interval','Dates & Intervals',$di_html,$box_size,false);
    $di_js = $di_select_obj->gen_date_interval_js();
    $this->add_custom_js($di_js);
    // Make this load form common
    $this->add_custom_js_link('/gsr/common/javascript/range-slider-master/js/rSlider.min.js');
    // Add specific CSS load here
    $this->add_custom_css_link('/gsr/common/css/range-slider-master/css/rSlider.min.css');
  $this->add_custom_css_link('/gsr/common/css/date-slider.css');
  }


  /**
  * Sets the export options present on the top left
  *
  * You don't have to pass all keys just the ones you want to set
  *
  * Options: [Default for all is true]<br>
  * - 'excel' => true/false
  * - 'pdf' => true/false
  * - 'print' => true/false
  * - 'help' => true/false
  *
  * @param array $parameters
  */
  public function set_export_icons($parameters = array()){
    if(!is_array($parameters)) return 'Invalid Parameters set_export_icons!';
    if(!array_key_exists('csv',$parameters)) $parameters['csv'] = false;    // JJ added 20130410 for Ticket Search use.
    if(!array_key_exists('excel',$parameters)) $parameters['excel'] = true;
    if(!array_key_exists('pdf',$parameters)) $parameters['pdf'] = true;
    if(!array_key_exists('print',$parameters)) $parameters['print'] = true;
    if(!array_key_exists('help',$parameters)) $parameters['help'] = true;
    if(!array_key_exists('csv_div',$parameters)) $parameters['csv_div'] = false;
    $this->_export_options = $parameters;
  }

  protected function _gen_export_icons_js(){
    $js = '';
    if(is_array($this->_export_options)){
      foreach($this->_export_options as $key => $value){
        if($value)
        {
          switch ($key)
          {
            case "print":
            {
              $js .= '
                $("#'.$key.'_export_icon").click(function(){
                    set_input_value("#export_action_value","'.$key.'");
                    window.frames.data_iframe.focus();
                    window.frames.data_iframe.print();
                  });
                ';
              break;
            }
            case "html":
            {
              $js .= '
                $("#'.$key.'_export_icon").click(function(){
                    set_input_value("#export_action_value","'.$key.'");
                    DoSubmission(true,false);
                  });
                ';
              break;
            }
            case "help":
            {
              $js .= '
              $("#'.$key.'_export_icon").click(function(){
                      var app_title = $("#menu_selected_input").val();
                      app_title = app_title.toLowerCase();
                      app_title = app_title.replace(/^\s+|\s+$/g,"");
                      app_title = app_title.replace(/ /g,"-");
                      app_title = app_title.replace(/&/g,"a");
                      window.open("'.GSR_HELP_LINK.'"+app_title);
                    });
              ';


              break;
            }
            case "csv_div":
            {
              // Do nothing.
              break;
            }
            case "csv":
            {
              $js .= '
                 $("#'.$key.'_export_icon").click(function(){
                      ';
              if ($this->_export_options['csv_div']) {
                // Display a div that slowly fades.
                $js .= '
                  var display_div =  document.createElement("div");
                  var window_width = $( window ).width();
                  display_div.className = "top_csv_export_notification_div";
                  display_div.style.posLeft = window_width-270;
                  display_div.style.left = (window_width-270) + "px";
                  display_div.innerHTML = "<table border=0><tr><td>Starting the CSV download.</td>"
                  +"<td rowspan=2><img src='."'/gsr/common/images/progress_wheel.gif'".' width='."'30'".' /></td></tr>"
                  +"<tr><td>Please wait.</td></tr></table>";
                  document.body.appendChild(display_div);
                  setTimeout(function() {
                    $(display_div).fadeOut(2000,function() {
                      $(display_div).remove();
                    });
                  },5000);
                ';
              }
              $js .= '// are we running ie6?
                      ie6 = DetectIE6();
                    if(!ie6) target = $("#viewer_form").attr("target");
                    if(!ie6)  $("#viewer_form").attr("target","_blank");
                    set_input_value("#export_action_value","'.$key.'");
                    DoSubmission(true,false);
                    if(!ie6) $("#viewer_form").attr("target",target);
                  });
                ';
              break;
            }
            default:
            {
              $js .= '
                 $("#'.$key.'_export_icon").click(function(){
                      // are we running ie6?
                      ie6 = DetectIE6();
                    if(!ie6) target = $("#viewer_form").attr("target");
                    if(!ie6)  $("#viewer_form").attr("target","_blank");
                    set_input_value("#export_action_value","'.$key.'");
                    DoSubmission(true,false);
                    if(!ie6) $("#viewer_form").attr("target",target);
                  });
                ';
              break;
            }
          }
        }

      }
    }
    return $js;
  }

  protected function _gen_saved_selections_controls(){
    $gui = new gsr_gui_class();
    $ss_id = 'top_ss';


    $html = '
        <div class="top_ss">
        <table id="top_ss_data_tb">
        <tr><td class="top_ss_white_bgr" colspan="2">User:&nbsp;&nbsp;<span id="top_ss_data_user_id">&nbsp;</span></td></tr>
        <tr>
          <td class="top_ss_white_bgr" rowspan="6">
          '.$gui->gen_form_select(array(
                    'id' => "top_ss_selections",
                    'font_size' => 'big',
                    'size' => 18,
                    'title' => 'Your saved selections:',
                    'option_value_array'=> array()
                    )).'
        <center><div id="ss_delete_selected">Delete Selected</div></center>
          </td>
          <td></td>
        </tr>
        <tr>
        <td class="top_ss_white_bgr"><center>'.$gui->gen_glass_button('top_ss_use_sel_b','Use Selection',150).'</center>
        </td></tr>
        <tr><td class="top_ss_white_bgr"><center>
        '.$gui->get_form_input_text(array(
            'id' => "top_ss_input_save",
            'font_size' => 'big',
            'maxlength' => 30,
            'size' => 40,
            'value' => 'Type in selection name ...',
            'title' => 'Save New Selection:'
            )
        ).'</center>
        <center style="margin-top:5px;">'.$gui->gen_glass_button('top_ss_new_sel_b','Save Selection',150).'
        </center>
        </td></tr>
        <tr><td class="top_ss_white_bgr"><center>'.$gui->gen_glass_button('top_ss_update_sel_b','Update Selection',150).'</center></td></tr>
        <tr><td class="top_ss_white_bgr">
        <div id="top_ss_status_icon" class="top_ss_empty_icon"></div>
        <div id="top_ss_message" class="top_ss_message"></div>
        </td></tr>
        <tr><td class="top_ss_white_bgr"><center>
        <span id="ss_log_as_diff_user">Switch User</span>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<span id="ss_log_out">Log out</span>
        </center></td></tr>
        </table>
        <table id="top_ss_login_tb">
          <tr>
            <td class="top_ss_white_bgr">
            <span class="top_ss_title">Please login with your email address:</span><br><br>
              '.$gui->get_form_input_text(array(
                  'id' => "top_ss_input_login",
                  'font_size' => 'big',
                  'maxlength' => 30,
                  'size' => 30,
                  'value' => 'Type in your email address ...'
                  )
              ).'<b>@abbott.com</b><br>
              <span id="top_ss_input_login_error" class="gui_error_message">&nbsp;</span><br>
              <center>'.$gui->gen_glass_button('top_ss_sel_login_b','Send Login Info',150).'</center><br>
              <span id="top_ss_have_auth_code">Already have authorization code? Click HERE.</span><br>
              <span id="top_ss_sel_login_help" class="gui_help_message">&nbsp;</span>
            </td>
          </tr>
          <tr><td class="top_ss_white_bgr" id="top_ss_auth_dialog">
            '.$gui->get_form_input_text(array(
                  'id' => "top_ss_input_auth_code",
                  'font_size' => 'big',
                  'maxlength' => 20,
                  'size' => 30,
                  'value' => '',
                  'title' => 'Please enter your authorization code below:'
                  )
              ).'
          <center>'.$gui->gen_glass_button('top_ss_sel_auth_b','Authenticate',150).'</center>
          </td></tr>

          <tr><td class="top_ss_white_bgr"><b>Why am I being asked to login?</b><br>
          <ul>
            <li>You may not have an account with us yet. Type in your email address and we will send you authentication email.</li>
            <li>We use browser cookie to authenticate, maybe the cookie got deleted. Type in your email address and we will send you authentication email.</li>
            <li>You want to login as a different user on this computer. Type in your email address and we will send you authentication email.</li>
            <li>You just want to authenticate another computer with your account. Type in your email address and we will send you authentication email.</li>
          </ul>
          </td></tr>
        </table>
        <!-- optional paramaters dialog -->
        <div style="display:none;" id="top_ss_parm_tb"></div>
        </div>


    ';


    $ss_box = $gui->div_box(array(
      'div_id' => "top_ss_divbox",
      'title' => 'Saved Selections',
      'html' => $html,
      'hidden' => true,
      'width' => 490
    ));
    $this->add_custom_bottom_html($ss_box);

    $js = '
      top_ss_msg_id = "#top_ss_message span";
      $("#top_ss_divbox_title_close").click(function(){$("#top_saved_selections_button").click();});
      $("#top_saved_selections_button").click(function(){
        hide_other_selectors("#top_ss_divbox");
        offset = $(this).offset();
        of_left = offset.left - 450;

        $("#top_ss_divbox").css("left",of_left);
        $("#top_ss_divbox").css("top",70);
        $("#top_ss_divbox").toggle();
      });
      $("#top_ss_title_close").click(function(){$("#top_saved_selections_button").click();});


      $("#top_ss_input_login").click(function(){
        if($(this).val() == "Type in your email address ...") $(this).val("");
      });
      $("#top_ss_input_save").click(function(){
        if($(this).val() == "Type in selection name ...") $(this).val("");
      });
      $("#top_ss_input_login").keypress(function(){
        val = $(this).val();
        if( (val.indexOf("@") != -1) ) {
          $("#top_ss_input_login_error").html("Please enter a valid email address!");
          $(this).data("valid","false");
        }
        else {
          $("#top_ss_input_login_error").html("");
          $(this).data("valid","true");
        }
      });

      $("#top_ss_input_login").keydown(function(){$("#top_ss_input_login").keypress();});
      $("#top_ss_input_login").keyup(function(){$("#top_ss_input_login").keypress();});

      $("#top_ss_sel_login_b").click(function(){
        email_input = $("#top_ss_input_login");
        if((email_input.data("valid") == "true") && (email_input.val().length > 3 ) ) {
          $("#top_ss_auth_dialog").slideDown("fast");
          $("#top_ss_input_auth_code").focus();
          ss_send_email_auth(email_input.val()+"@abbott.com");
        }
        else $("#top_ss_input_login_error").html("Please enter a valid email address!");
      });
      $("#top_ss_sel_auth_b").click(function(){
        validate_auth_string($("#top_ss_input_login").val()+"@abbott.com",$("#top_ss_input_auth_code").val());
      });
      $("#ss_log_as_diff_user").click(function(){
        ss_login_as_diff_user();
      });
      $("#top_ss_have_auth_code").click(function(){
        $("#top_ss_auth_dialog").slideDown("fast");
        $("#top_ss_input_auth_code").focus();
      });
      $("#ss_log_out").click(function(){
        ss_logout();
      });

      $("#top_saved_selections_button").mouseover(function(){$(this).attr("class","top_saved_selections_button_hover");});
      $("#top_saved_selections_button").mouseout(function(){$(this).attr("class","top_saved_selections_button");});

      $("#top_ss_new_sel_b").click(function(){
        ss_object_run.action("new selection");
      });

      $("#top_ss_update_sel_b").click(function(){
        ss_object_run.action("update selection");
      });

      $("#top_ss_use_sel_b").click(function(){
        sel = $("#top_ss_selections :selected");
        ss_display_message("","N");
        if(sel.length > 0){
          $("#top_saved_selections_button").click();
          get = $("#top_ss_selections").data(sel.val());
            var loc = window.location;
            window.location = loc.protocol + "//" + loc.host + loc.pathname + "?"+get;
        }
        else {
          ss_display_message("Please select one from your selections.","W");
        }
      });



      $("#ss_delete_selected").click(function(){
         sel = $("#top_ss_selections :selected");
        ss_display_message("","N");
        if(sel.length > 0){
          if(ss_delete_selection(sel.val())) ss_display_message("Selection Deleted","O");
        }
        else {
          ss_display_message("Please select one from your selections.","W");
        }
      });

      ss_get_user_data("record_access");
      selectors_array[99] = "#top_ss_divbox";
    ';
    $this->add_custom_js($js);
    $this->add_custom_js_link("/gsr/common/javascript/saved_selections.js");

    $top_button = '<div class="top_saved_selections_button" id="top_saved_selections_button">Saved Selections</div>';
    return $top_button;
  }

  /**
  * Generates final HTML for the viewer
  *
  * Capable of hiding viewer around the iframe by passing "gssmetrics" paramaters in the URL
  *
  * @param boolean $return true [default]: return the HTML as a string, false: echo the HTML
  */
  public function get_final_html($return = true){



    $this->_clear_error_status();
    $this->_final_html = $this->_viewer_template_html;

    if($this->_window_title === '') $this->_window_title = $this->_app_name;
    $this->_final_html = str_replace('[[PAGE_TITLE]]',$this->_window_title,$this->_final_html);
    $this->_final_html = str_replace('[[APP_NAME]]',$this->_app_name,$this->_final_html);

    $this->_final_html = str_replace('[[FORM_ACTION]]',$this->form_action,$this->_final_html);

    $lmenu_w  = '';
    $lmenu_tw  = '';
    if($this->left_menu_width !== '' && is_int($this->left_menu_width)) {
      $lmenu_tw = 'style="width: '.$this->left_menu_width.'px"';
      $sub_width = $this->left_menu_width - 10;
      $lmenu_w = 'style="width: '.$sub_width.'px"';
    }

    $this->_final_html = str_replace('[[LEFT_MENU_TOTAL_WIDTH]]',$lmenu_tw,$this->_final_html);
    $this->_final_html = str_replace('[[LEFT_MENU_WIDTH]]',$lmenu_w,$this->_final_html);

    $this->_final_html = str_replace('[[TOP_MENU_CODE]]',$this->_gsr_top_menu('[GSR_ADMIN].[dbo].[GSR_ADMIN_GUI_MENUS]',SERVER_ENVIRONMENT),$this->_final_html);
    $this->_final_html = str_replace('[[TOP_SELECTORS_WIDTH]]',$this->_get_top_selectors_table_width(),$this->_final_html);
    $this->_final_html = str_replace('[[TOP_NAVIGATION_SELECTORS_CODE]]',$this->_get_top_navigation_selectors_code(),$this->_final_html);
    $this->_final_html = str_replace('[[TOP_RIGHT_NAVIGATION__CODE]]','',$this->_final_html);
    $this->_final_html = str_replace('[[IFRAME_SRC]]',$this->iframe_src,$this->_final_html);

    $this->_final_html = str_replace('[[LEFT_CONTROLS_CONTENT]]',$this->_get_left_menu_sections_html(),$this->_final_html);
    $this->_final_html = str_replace('[[TOP_NAVIGATION_SELECTORS_DIV_BOXES]]',$this->_get_top_navigation_selectors_div_boxes(),$this->_final_html);
    $this->_final_html = str_replace('[[TOP_NAVIGATION_SELECTORS_JS]]',$this->_get_top_navigation_selectors_js(),$this->_final_html);
    // display only selected export icons
    $export_icons = $this->gsr_gui_obj->get_export_icons($this->_export_options);
    $this->_final_html = str_replace('[[EXPORT_ICONS]]',$export_icons,$this->_final_html);



    $this->_final_html = str_replace('[[SAVED_SELECTIONS]]',$this->_gen_saved_selections_controls(),$this->_final_html);

    $css_links = "";
    foreach($this->_custom_css_link as $link) $css_links .= '<link rel="stylesheet" type="text/css" href="'.$link.'">';
    $this->_final_html = str_replace('[[CUSTOM_CSS_LINKS]]',$css_links,$this->_final_html);

    // check if autosubmit is set in request
    if(isset($_REQUEST["auto_submit"]) && $_REQUEST["auto_submit"] == "on") $this->add_custom_js('$("#menu_hide").click();');

    // build custom js code
    $custom_js = $this->_gen_export_icons_js();
    foreach($this->custom_js as $js) $custom_js .= $js;
    $this->_final_html = str_replace('[[CUSTOM_BOTTOM_JS]]',$custom_js,$this->_final_html);
    $custom_html = '';
    if(isset($_REQUEST["gssmetrics"])) {
       $custom_html = '<style type="text/css">
       #top_controls {display:none;};
       #left_controls {display:none;};
       #left_controls_submit {display:none;};
       #left_controls_autosubmit {display:none;};
       #page_content {border: none;};
       </style>
       <img src="/gsr/gss_metrics_display/gss_msetings.png" border="0" id="scrollingDiv" style="position:absolute;display:none;">
       <script type="text/javascript" src="/gsr/gss_metrics_display/gss_metrics.js"></script>
       ';
    }

    foreach($this->_custom_bottom_html as $html_add) $custom_html .= $html_add;
    $this->_final_html = str_replace('[[CUSTOM_BOTTOM_HTML]]',$custom_html,$this->_final_html);
    $custom_js_links = '';
    foreach($this->_custom_js_links as $js_link) $custom_js_links .= '<script type="text/javascript" src="'.$js_link.'"></script>';
    $this->_final_html = str_replace('[[CUSTOM_JS_LINKS]]',$custom_js_links,$this->_final_html);


    if($return) return $this->_final_html;
    else echo $this->_final_html;
  }
 }
?>