<?php
  /**
  * date_interval_viewer.php
  *
  *
  * $Revision: 1950 $
  *
  * $Date: 2019-03-04 08:09:57 -0500 (Mon, 04 Mar 2019) $
  *
  * $Id: date_interval_viewer.php 1950 2019-03-04 13:09:57Z fiertekr $
  * @package crl_mgmt
  */
  require_once('gsr_environment.php');
  // use the viewer class
  require_once $_SERVER['DOCUMENT_ROOT'] . '/gsr/_robert/common/class/gsr_main_viewer_class.php';
  require_once( GSR_DOCUMENT_ROOT .  '/gsr/common/class/gsr_utilities.php');

  // Instantiate the core db connectivity class
  $show_sql = isset($_REQUEST['show_sql']);
  if ($show_sql)
  {
    setcookie('show_sql','true',0);
  } else
  {
    setcookie('show_sql','',time()-86400); // Delete cookie.
  }

  $db_obj = new gsr_sqlsrv_db_class();
  if ($show_sql)
  {
    $db_obj->set_history_on();
  }
  // Utilities class
  $util_obj = new gsr_utilities();
  // Instantiate the viewer and gui classes
  $gsr_viewer = new gsr_main_viewer_class(GSR_DOCUMENT_CONFIG.'gsr_main_viewer_class_html_template_updated_2019.php');
//  $gsr_viewer = new gsr_main_viewer_class();



  $gsr_gui = new gsr_gui_class();


  // Set a flag if we came if fresh, not from saved selections, not passed in from the URL line
  if(isset($_REQUEST['notfresh'])){
    $default = false;
  } else {
    $default = true;
  }

  if(!isset($_REQUEST['locallevel'])){
    $_REQUEST['locallevel'] = '000';
  }
  // Instrument widget.
  $gsr_viewer->add_top_navigation_instruments(array('db_obj'=>$db_obj,
                          'request'=>$_REQUEST,
                          'incl_pl_group'=>true,
                          'multi_select'=>true,
                          'incl_legacy_checkbox'=>false));


  $gsr_viewer->set_application_name('Contact Center Reports');
  $gsr_viewer->set_form_action('call_center_parse.php');
  $gsr_viewer->set_iframe_src('call_center_parse.php');

  //$gsr_viewer->set_form_action('test.php');
  //$gsr_viewer->set_iframe_src('test.php');
  $gsr_viewer->set_left_menu_width(200);
  $gsr_viewer->set_window_title('Contact Center Reports');
  //$gsr_viewer->add_top_navigation_orgs(NULL,TRUE);
  //$gsr_viewer->add_top_navigation_orgs( $_REQUEST, true,  true, 1,0, true);
  // No Local Levels
  $gsr_viewer->add_top_navigation_orgs( false, false,  true, 1,0,true);
  // Servicing Group Widget
  $gsr_viewer->add_top_navigation_servicing_groups(array('db_obj'=>$db_obj,
                          'request'=>$_REQUEST,
                          'multi_select'=>true,));


  $gsr_viewer->add_top_navigation_business_segments($parameters = array('db_obj' => $db_obj
                                   ,'request' => $_REQUEST ) );

  // get dates for populating the top level date interval selector
  $max_date = date('Ym');

//echo $_REQUEST["di_min"] ." :2<br/>";

  if ( isset($_REQUEST["di_min"]) ) { $di_min_raw = $_REQUEST["di_min"]; } else { $di_min_raw = "201801"; }

  $norm_min = $norm_max = 0;
  if ( strlen($di_min_raw) == 4 ) {
    $norm_min = $di_min_raw."0101";
    $di_int = "y";
  } // year 2018
  if ( stripos($di_min_raw, 'q') !== false ) {  // quarter 2018Q1
    $q_min = substr($di_min_raw,5,1);
    if ( $q_min == 1 ) { $m_min = "01"; } elseif ( $q_min == 2 ) { $m_min = "04"; } elseif ( $q_min == 3 ) { $m_min = "07"; } else { $m_min = "10"; }
    $norm_min = substr($di_min_raw,0,4)."".$m_min."01";
    $di_int = "q";
  }
  if ( strlen($di_min_raw) == 6 && stripos($di_min_raw, 'q') === false ) {
    $norm_min = substr($di_min_raw,0,4)."".substr($di_min_raw,4,2)."01";
    $di_int = "m";
  }  // month  201804
  if ( stripos($di_min_raw, 'w') !== false ) {  // week  2018w42
    $gendate = new DateTime();
    $gendate->setISODate(substr($di_min_raw,0,4),substr($di_min_raw,5,2),1);
    $norm_min = $gendate->format('Ymd');
    $di_int = "w";
  }
  if ( stripos($di_min_raw, '-') !== false ) {
    $norm_min = substr($di_min_raw,0,4)."".substr($di_min_raw,5,2)."".substr($di_min_raw,8,2);
    $di_int = "d";
  }

  $di_min = "20180101"; // absolute min for date interval selector

//echo $_REQUEST["di_min"] ." :2a<br/>";

  $first_per = $util_obj->add_months_to_period($max_date,-13);
  $di_first = substr($first_per,0,4)."-".substr($first_per,4)."-01";

  $di_max = $util_obj->add_months_to_period($max_date,-1);
//$di_max = "201812";

  $last_per = $util_obj->add_months_to_period($max_date,-1); $mo_num = ltrim(substr($last_per,4),'0'); $day_num = cal_days_in_month(CAL_GREGORIAN, $mo_num, substr($last_per,0,4));
  $di_last = substr($last_per,0,4)."-".substr($last_per,4)."-".$day_num;

  // Override default settings if the di_min and/or di_max is passed in.
  if(isset($_REQUEST["di_min"])){

//echo $_REQUEST["di_min"] ." :2b<br/>";

    $in_start_set = $_REQUEST["di_min"];

    //Check if passed in value is too early, if it is we will set it to the earliest.
    ($norm_min < $di_min)? $norm_min = $di_min : $di_first = $norm_min;
  }

  if(isset($_REQUEST["di_max"])){
    $in_end_set = $_REQUEST["di_max"];
    //Check if passed in value is too early, if it is we will set it to the earliest.
    ($in_end_set > $di_last)? $_REQUEST["di_max"] = $di_last: $di_last = $in_end_set;
  }

  // IF YOU WANT TO EXPLICITLY exclude certain date interval types.
  // Choice is any mix of: y,q,m,w,d or "000" for all, or leave blank.
  $_REQUEST["di_display"] = "yqmw";

//Set default Interval
//$default_interval = 'm';
//$default_di_min_set = 201801;

//echo $di_min ." :2c<br/>";

  $gsr_viewer->add_top_navigation_date_interval(array('db_obj'=>$db_obj,
                            'request'=>$_REQUEST,
                            'di_min'=>$di_min,
                            'di_max'=>$di_max,
                            'di_first'=>$di_first,
                            'di_last'=>$di_last
                             ));

  // Add top selector box for handling dates
  // get dates for populating the top level date selector
//  $max_date  = date('Ym');
//  $min_date  = $util_obj->add_months_to_period($max_date,-25);
//  $start_set = $util_obj->add_months_to_period($max_date,-13);
//  $max_set   = $util_obj->add_months_to_period($max_date,-1);
//  $end_set   = $util_obj->add_months_to_period($max_date,-1);
//
//  // Override default settings if the minperiod and/or maxperiod is passed in.
//  if(isset($_REQUEST["minperiod"])){
//    $in_start_set = $_REQUEST["minperiod"];
//    //Check if passed in value is too early, if it is we will set it to the earliest.
//    ($in_start_set < $min_date)? $_REQUEST["minperiod"] = $min_date: $start_set = $in_start_set;
//  }
//  if(isset($_REQUEST["maxperiod"])){
//    $in_end_set = $_REQUEST["maxperiod"];
//    //Check if passed in value is too early, if it is we will set it to the earliest.
//    ($in_end_set > $end_set)? $_REQUEST["maxperiod"] = $end_set: $end_set = $in_end_set;
//  }
//
//
//  $gsr_viewer->add_top_navigation_dates(array('end_set'=>$end_set,
//                        'max_date'=>$max_set,
//                        'min_date'=>$min_date,
//                        'start_set'=>$start_set));

  // Choose export icons to display
  $gsr_viewer->set_export_icons(
  array(
      'excel' =>  false,
      'pdf'   =>  false,
      'print' =>  true,
      'help'  =>  true
    )
  );


//print_r($_REQUEST);


 // Need to do some special things for passed in parms for
 // selection boxes that are ajaxed in.
 if($default){
   $app_submit_js = 'var app_submit = false;';
 }  else {
   $app_submit_js = 'var app_submit = true;';
 }
 $parm_css = "";
 if(isset($_REQUEST['ind_opened'])){
  $person_data = (isset($_REQUEST['ind_opened'])) ? explode('::',$_REQUEST['ind_opened']) : '::';
  $person = $person_data[0];
  $person_name = $person_data[1];
  $ind_opened_js = 'var parm_css = "'.$_REQUEST['ind_opened'].'";';
 }  else {
  $ind_opened_js = 'var parm_css = "***";';
 }
 $selected_cust_array = (isset($_REQUEST['cust'])) ? $_REQUEST['cust']: '';
 $parm_cust = "";
 if(is_array($selected_cust_array)){
   // Is this array empty
   if(count($selected_cust_array) == 0){
     $ind_hand_js_array = "var parm_cust_array = new Array();\n";
   } else {
     $ind_hand_js_array = "var parm_cust_array = new Array();\n";
    foreach($selected_cust_array as $k=>$v){
      $ind_hand_js_array .= "parm_cust_array[$k] = \"$v\";\n";
    }
   }
 } else {
    $ind_hand_js_array = "var parm_cust_array = new Array();\n";
 }

  // Javascript control section
  $inst_js = '
  // The following is so Auto Submit ON will work
  $("#report_type").change(function(){
    DoSubmission();
  });
  $("#radio_ctry").change(function(){
    DoSubmission();
  });
  $("#radio_ind").change(function(){
    DoSubmission();
  });
  $("#comp").change(function(){
    DoSubmission();
  });
  $("#inq").change(function(){
    DoSubmission();
  });
  $("#sd").change(function(){
    DoSubmission();
  });
  $("#sp").change(function(){
    DoSubmission();
  });
  $("#t_clsd").change(function(){
    DoSubmission();
  });
  $("#t_open").change(function(){
    DoSubmission();
  });
  $("#no_parts").change(function(){
    DoSubmission();
  });
  $("#by_oper").change(function(){
    DoSubmission();
  });
  $("#by_phm").change(function(){
    DoSubmission();
  });
  $("#by_tso").change(function(){
    DoSubmission();
  });
  $("#slider").change(function(){
    DoSubmission();
  });
  $("#centers").change(function(){
    DoSubmission();
  });
  $("#ind_hand").change(function(){
    DoSubmission();
  });


  $("input[name=sub_type]").change(function () {
    // If Report Type changes we need to hide show some stuff and
    // ajax in list ot the list boxes using jCombo

    // Get sub_type
    var sub_type = $("input[name=sub_type]:checked").attr("value");
    //alert(sub_type);
    // Get items needed for the ajax URL
    // Get selected org
    var org = $("#org").val();
    // Get selected country
    var country = $("#country").val();

    // Get Date Interval stuff

//    var di_min = $("#jquery_di_data #di_min").val();
//    var di_max = $("#jquery_di_data #di_max").val();
//    var di_int = $("#jquery_di_data #di_int").val();
//    var di_first = $("#jquery_di_data #di_first").val();
//    var di_last = $("#jquery_di_data #di_last").val();
//    var di_display = $("#jquery_di_data #di_display").val();

    // get ticket Status
    var t_clsd =  $("#t_clsd").attr("checked")?1:0;
    var t_open =  $("#t_open").attr("checked")?1:0;
    // Get Ticket Types
    var comp =  $("#comp").attr("checked")?1:0;
    var inq =  $("#inq").attr("checked")?1:0;
    var sd =  $("#sd").attr("checked")?1:0;
    var sp =  $("#sp").attr("checked")?1:0;
    // Build sg URL part
    var sg_url = "";
    var sg_a = $("#sg").val();
    $.each(sg_a, function(i, val) {
      sg_url = sg_url + "&sg[]=" +val;

    });
    // Build bs URL part
    var bs_url = "";
    var bs_a = $("#bs").val();
    $.each(bs_a, function(i, val) {
      bs_url = bs_url + "&bs[]=" +val;

    });
    // build url
    if ( typeof di_min === "undefined" ) { di_min = ""; }
    if ( typeof di_max === "undefined" ) { di_max = ""; }
    if ( typeof di_int === "undefined" ) { di_int = ""; }
    if ( typeof di_first === "undefined" ) { di_first = ""; }
    if ( typeof di_last === "undefined" ) { di_last = ""; }
    if ( typeof di_display === "undefined" ) { di_display = ""; }

    var url = "org="+org+"&"+"country="+country+"&"+"t_clsd="+t_clsd+"&"+"inq="+inq+"&"+"sp="+sp+"&"+"sd="+sd+"&"+"t_open="+t_open+"&"+"comp="+comp+sg_url+bs_url+"&di_min="+di_min+"&di_max="+di_max+"&di_int="+di_int+"&di_first="+di_first+"&di_last="+di_last+"&di_display="+di_display;



     // Show or hide the Individual selections.
    switch (sub_type){
       case "ind":
        // Show the Individual section
        $("#ind_views").show();
        $("#centers_views").hide();


        $("#ind_hand_loading").show();
        $("#ind_hand").jCombo("get_ind_hand.php?"+ url );

        break;
       case "cntr":
        // Show the Call/Smart Center section
        $("#ind_views").hide();
        $("#centers_views").show();

        $("#centers_loading").show();
        $("#centers").jCombo("get_centers.php?"+ url );

        break;
       case "ctry":
        // Hide the customer selector
        $("#ind_views").hide();
        $("#centers_views").hide();
        break;
    }
    DoSubmission();
  });



  // If Org, Business Segment or Servicing Groups change we
  // Need to fire off the reload of the individual selects.
  $("#org,#country,#locallevel,#bs,#date_interval").change(function(){
    $("input[name=sub_type]").trigger("change");
  });
  // Document Ready
  $(function() {
    '.$app_submit_js.'
    $("input[name=sub_type]").trigger("change");
    // Set inital widths of the select boxes
    $("#centers").width(170);
    $("#ind_opened").width(175);
    $("#ind_hand").width(175);
  });  // End of Document Ready

  ';


  $gsr_viewer->add_custom_js($inst_js);

  //$gsr_viewer->add_custom_js_link("/gsr/common/javascript/jquery-ui.js");

  //$gsr_viewer->add_custom_js_link('range-slider-master/js/rSlider.min.js');

  //$gsr_viewer->add_custom_js_link("javascript/jquery-1.8.0.min.js");
  //$gsr_viewer->add_custom_js_link('https://www.jeasyui.com/easyui/jquery.min.js');

  $gsr_viewer->add_custom_js_link("javascript/jquery.jCombo.js");

  $gsr_viewer->set_load_indicator_on();


//===================================================================
//  L E F T   S I D E   M E N U
//===================================================================


// Search By - Select Drop Down menu
$search_by_array = array( 'cust_name'=>'Customer',
              'cust'=>'Customer Number',
              'css'=>'Employee(login)',
              'css_n'=>'Employee(name)');
$selected_search_by  = (isset($_REQUEST['search_by'])) ? $_REQUEST['search_by']: '';

//$selected_sg_select_array  = (isset($_REQUEST['sg'])) ? $_REQUEST['sg']: array('Abbott_ADD');
$centers_array = array();
$centers_sel  = (isset($_REQUEST['centers'])) ? $_REQUEST['centers']: '';

$ind_open_array = array();
$ind_open  = (isset($_REQUEST['ind_open'])) ? $_REQUEST['ind_open']: '';

$ind_hand_array = array();
$ind_hand  = (isset($_REQUEST['ind_hand'])) ? $_REQUEST['ind_hand']: '';


 /////////////////////////////////////////////////////
 // Arrays used to build parts of the left
 // side menu elements
 ////////////////////////////////////////////////////

 $report_type = array( 'serv_perf'=>'Service Performance',
             'prr_ex'=>'PRR by Exp Code'//,
            // 'phone_metrics'=>'Phone Metrics'
             );
 $selected_report_type  = (isset($_REQUEST['report_type'])) ? $_REQUEST['report_type']: '';

 // Radio Buttons
 $rep_sub_type_ctry_array = array("id"=>"radio_ctry","value"=>"ctry","title"=>"By Location","title_position"=>"right","name"=>'sub_type');
 $rep_sub_type_ind_array  = array("id"=>"radio_ind","value"=>"ind","title"=>"By Individual","title_position"=>"right","name"=>'sub_type');
 $rep_sub_type_cntr_array = array("id"=>"radio_cntr","value"=>"cntr","title"=>"By Call/Smart Center","title_position"=>"right","name"=>'sub_type');
 //$rep_sub_type_ctry_array  = array("value"=>"i","title"=>"By Individual","title_position"=>"right","name"=>'by');

 $sub_type  = (isset($_REQUEST["sub_type"])) ? $_REQUEST["sub_type"]: "ctry";
($sub_type == "ctry") ? $rep_sub_type_ctry_array['checked'] = '1'  :  $junk = 1;
($sub_type == "ind")  ? $rep_sub_type_ind_array['checked']  = '1'  :  $junk = 1;
($sub_type == "cntr") ? $rep_sub_type_cntr_array['checked'] = '1'  :  $junk = 1;


 // Checkboxes
 $complaint_array = array('id'=>'comp','name'=>'comp','value'=>'1','title'=>'Complaint','title_position'=>'right');
 $inquiry_array = array('id'=>'inq','name'=>'inq','value'=>'1','title'=>'Inquiry','title_position'=>'right');
 $s_demand_array = array('id'=>'sd','name'=>'sd','value'=>'1','title'=>'Service Demand','title_position'=>'right');
 $s_planned_array = array('id'=>'sp','name'=>'sp','value'=>'1','title'=>'Service Planned','title_position'=>'right');

 $tick_closed_array = array('id'=>'t_clsd','name'=>'t_clsd','value'=>'1','title'=>'Closed','title_position'=>'right');
 $tick_open_array   = array('id'=>'t_open','name'=>'t_open','value'=>'1','title'=>'Open','title_position'=>'right');
 $tick_void_array   = array('id'=>'t_void','name'=>'t_void','value'=>'1','title'=>'Voided','title_position'=>'right');

 $open_by_oper_array = array('id'=>'by_oper','name'=>'by_oper','value'=>'1','title'=>'Operator','title_position'=>'right');
 $open_by_phm_array = array('id'=>'by_phm','name'=>'by_phm','value'=>'1','title'=>'PHM/POM','title_position'=>'right');
 $open_by_tso_array = array('id'=>'by_tso','name'=>'by_tso','value'=>'1','title'=>'TSO','title_position'=>'right');

 $no_parts_used_array = array('id'=>'no_parts','name'=>'no_parts','value'=>'1','title'=>'No Parts Replaced','title_position'=>'right');
 //$report_sub_type =


 if($default){
  // Here is where you would set a checkbox to "Checked" as a default
  // Here is an example: If we wanted the defaultot have the TSB mandatory checkbox checked we will do.
  // $tsb_man_array['checked'] = '1';
  $complaint_array['checked'] = '1';
  $s_demand_array['checked']  = '1';
  //$s_planned_array['checked'] = '1';

  $tick_closed_array['checked'] = '1';

  $open_by_oper_array['checked'] = '1';
  $open_by_phm_array['checked']  = '1';
  $open_by_tso_array['checked']  = '1';

 } else {
  (isset($_REQUEST["comp"]))    ? $complaint_array['checked'] = '1'     : $junk = 0;
  (isset($_REQUEST["inq"]))     ? $inquiry_array['checked'] = '1'     : $junk = 0;
  (isset($_REQUEST["sd"]))    ? $s_demand_array['checked'] = '1'    : $junk = 0;
  (isset($_REQUEST["sp"]))    ? $s_planned_array['checked'] = '1'     : $junk = 0;

  (isset($_REQUEST["t_clsd"]))  ? $tick_closed_array['checked'] = '1'   : $junk = 0;
  (isset($_REQUEST["no_parts"]))  ? $no_parts_used_array['checked'] = '1'  : $junk = 0;
  (isset($_REQUEST["t_open"]))  ? $tick_open_array['checked'] = '1'     : $junk = 0;
  (isset($_REQUEST["t_void"]))  ? $tick_void_array['checked'] = '1'     : $junk = 0;

  (isset($_REQUEST["by_oper"]))   ? $open_by_oper_array['checked'] = '1'  : $junk = 0;
  (isset($_REQUEST["by_phm"]))  ? $open_by_phm_array['checked'] = '1'   : $junk = 0;
  (isset($_REQUEST["by_tso"]))  ? $open_by_tso_array['checked'] = '1'   : $junk = 0;

 }

 /*
 // Search By section
$gsr_viewer->add_left_menu_section(
  array(
    'id'  => 'left_search_options',
    'title' => 'Search Options',
    'html'  => ''.
        $gsr_gui->gen_form_select(array('id'=>'search_by',
                  'option_value_array'=>$search_by_array,
                  'title'=>'Search By',
                  'multiple'=>FALSE,
                  'selected_value'=>$selected_search_by,
                  'size'=>1,
                  'name'=>'search_by')
                  ).
        $gsr_gui->get_form_input_text(array('id'=>'search_for',
                  'title'=>'Search: (* wild)',
                  'size'=>1,
                  'name'=>'search_for')
                  ).
        $gsr_gui->gen_classic_button('search_for_clear','Reset').
        $gsr_gui->gen_classic_button('search_for_submit','Search'),
    'border'=> 'true'
    )
  );

  */

  $gsr_viewer->add_left_menu_section(
  array(
    'id'  => 'opt',
    'title' => 'Options',
    'html'  => ''.        $gsr_gui->gen_form_select(array('id'=>'report_type',
                  'option_value_array'=>$report_type,
                  'title'=>'Report Type',
                  'multiple'=>false,
                  'selected_value'=>$selected_report_type,
                  'size'=>1,
                  'name'=>'report_type')
                  ).
                    $gsr_gui->get_form_input_radio($rep_sub_type_ctry_array).
                    $gsr_gui->get_form_input_radio($rep_sub_type_ind_array) .
                    $gsr_gui->get_form_input_radio($rep_sub_type_cntr_array)

                   . '<br><div id="ticket_type_checkboxes">
                  <span class="gui_select_title ticket-type">Ticket Type(s)</span>'.
                  $gsr_gui->get_form_input_checkbox($complaint_array).
                  $gsr_gui->get_form_input_checkbox($s_demand_array).
                  $gsr_gui->get_form_input_checkbox($s_planned_array).
                  $gsr_gui->get_form_input_checkbox($inquiry_array).
                  '</div>'

                  . '<br><div id="ticket_status_checkboxes">
                  <span class="gui_select_title ticket-status">Ticket Status</span>'.
                  $gsr_gui->get_form_input_checkbox($tick_closed_array).
                  $gsr_gui->get_form_input_checkbox($tick_open_array).
                  $gsr_gui->get_form_input_checkbox($tick_void_array).
                  '</div>'

                  . '<br><div id="ticket_openedby_checkboxes">
                  <span class="gui_select_title ticket-opened-by">Ticket Opened By</span>'.
                  $gsr_gui->get_form_input_checkbox($open_by_oper_array).
                  $gsr_gui->get_form_input_checkbox($open_by_phm_array).
                  $gsr_gui->get_form_input_checkbox($open_by_tso_array).
                  '</div>'

                  . '<br><div id="no_parts_used_checkboxe">
                  <span class="gui_select_title no-parts-used">Limit Tickets To</span>'.
                  $gsr_gui->get_form_input_checkbox($no_parts_used_array).
                  '</div>'


                  ,
          'border'=> 'true'
    )
  );
  $gsr_viewer->add_left_menu_section(
  array(
    'id'  => 'centers_views',
    'title' => 'Call/Smart Centers',
    'html'  => ''. '<div id="centers_open_div">' .
        $gsr_gui->gen_form_select(array('id'=>'centers',
                  'option_value_array'=>$ind_open_array,
                  'title'=>'Call/Smart Centers<span id="centers_loading" style="display: none;"><img src="/gsr/common/images/ripples.gif"></span>',
                  'multiple'=>TRUE,
                  'selected_value'=>$centers_sel,
                  'size'=>6,
                  'name'=>'centers[]')
                  ).
                   '</div>'
    )
  );
  $gsr_viewer->add_left_menu_section(
  array(
    'id'  => 'ind_views',
    'title' => 'Individual',
    'html'  => ''.  '<div id="ind_hand_div">' .
        $gsr_gui->gen_form_select(array('id'=>'ind_hand',
                  'option_value_array'=>$ind_hand_array,
                  'title'=>'Ticket Handled By<span id="ind_hand_loading" style="display: none;"><img src="/gsr/common/images/ripples.gif"></span>',
                  'multiple'=>true,
                  'selected_value'=>$ind_hand,
                  'size'=>6,
                  'name'=>'ind_hand[]')
                  ). '</div>' ,
          'border'=> 'true'
    )
  );

//  $gsr_viewer->add_left_menu_section(
//  array(
//    'id'  => 'chart',
//    'title' => 'Charting',
//    'html'  => ''.        $gsr_gui->gen_form_select(array('id'=>'charting_type',
//                  'option_value_array'=>$charting_type,
//                  'title'=>'Charting Type',
//                  'multiple'=>false,
//                  'selected_value'=>$sel_charting_type,
//                  'size'=>4,
//                  'name'=>'charting_type')
//                  ),
//          'border'=> 'true'
//    )
//  );



 $html = '<div><input type="hidden" name="notfresh" value="1" /></div>
       <div><input type="hidden" id="search_for_hidden" name="search_for_hidden" value="" /></div>';
  $gsr_viewer->add_custom_bottom_html($html);
  $gsr_viewer->add_custom_css_link('/gsr/call_center/call-center.css');

  //$gsr_viewer->add_custom_css_link('range-slider-master/css/rSlider.min.css');

  $gsr_viewer->get_final_html(false);

?>