<?php
  // load in environment constants.
  require_once('gsr_environment.php');

  // Includes DB conn, common utils, etc.
  // Required files to use
  require_once( GSR_DOCUMENT_ROOT . '/gsr/common/class/gsr_sqlsrv_db_class.php');
  require_once( GSR_DOCUMENT_ROOT . '/gsr/common/class/gsr_org_class.php');
  require_once( GSR_DOCUMENT_ROOT . '/gsr/common/class/gsr_utilities.php');
  require_once( GSR_DOCUMENT_ROOT . '/gsr/common/class/instrument_select_class.php');
  require_once( GSR_DOCUMENT_ROOT . '/gsr/common/class/business_segments_select_class.php');
  require_once( GSR_DOCUMENT_ROOT . '/gsr/common/class/servicing_groups_select_class.php');
  require_once( GSR_DOCUMENT_ROOT . '/gsr/_robert/common/class/date_interval_select_class.php');

  require_once('serv_perf_class.php');

  $page_refresh = 1;

  $is_IE = 0;
  $ua = htmlentities($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8');
  if (preg_match('~MSIE|Internet Explorer~i', $ua) || (strpos($ua, 'Trident/7.0') !== false && strpos($ua, 'rv:11.0') !== false)) {
    $is_IE = 1;
  }

  // REPORT TYPE
  //report_type -- serv_perf | prr_ex | phone_metrics
  $report_type = ( isset($_REQUEST["report_type"]) ) ? $_REQUEST["report_type"] : "serv_perf";
  //sub_type -- ctry | ind | cntr
  $sub_type = ( isset($_REQUEST["sub_type"]) ) ? $_REQUEST["sub_type"] : "ctry";

  $di_last_date = new DateTime("now");
  $di_last_date = $di_last_date->format("Y-m-d");
  $di_max = date("Ym", strtotime("-1 months"));
  if ( !isset($_REQUEST["di_min"]) ) { $_REQUEST["di_min"] = "201801"; }
  if ( !isset($_REQUEST["di_max"]) ) { $_REQUEST["di_max"] = $di_max; }
  if ( !isset($_REQUEST["di_int"]) ) { $_REQUEST["di_int"] = "m"; }
  if ( !isset($_REQUEST["di_first"]) ) { $_REQUEST["di_first"] = "2018-01-01"; }
  if ( !isset($_REQUEST["di_last"]) ) { $_REQUEST["di_last"] = $di_last_date; }
  if ( !isset($_REQUEST["di_display"]) ) { $_REQUEST["di_display"] = "000"; }

  // Instantiate the core db connectivity class
  $db_obj = new gsr_sqlsrv_db_class();
  $gsr_util = new gsr_utilities();
  $org_obj = new gsr_org_class($db_obj, $_REQUEST, true, true, 1, 1 );
  $bs_obj = new business_segments_select_class(array('db_obj'=>$db_obj,'request'=>$_REQUEST));
  $inst_select_obj = new instrument_select_class(array('db_obj'=>$db_obj,'request'=>$_REQUEST));
  $sg_obj = new servicing_groups_select_class(array('db_obj'=>$db_obj,'request'=>$_REQUEST));
  $di_obj = new date_interval_select_class(array('db_obj'=>$db_obj,'request'=>$_REQUEST));

  $org_data = $org_obj->get_selected_array();

  $org_selected = $org_data['selected_org'];
  $cc = $country_selected = $org_data['selected_country'];

  $where = 'WHERE 1=1 ';
  $where_sb = 'WHERE 1=1 ';

  // Build Where clause
  $org_where = $org_obj->get_org_sql('SS.country','SS.LocalLevel1','SS.LocalLevel2');
  $org_where_c = $org_obj->get_org_sql('c.[country code]');
  $bs_where = $bs_obj->get_calc_bs_sql('business_segment');
  $pl_where = $inst_select_obj->get_pl_sql('BestPL');

  if ( $sub_type == "ctry" ) { // by "ind" is set below, after $data is set
    // Get list of countries and country codes for selected.
    $sql = "
    select [Country Code] as country, [Country Description] as c_desc
    from reliability.dbo.country c
    where 1=1
    $org_where_c
    order by 2
    ";
    $rs_data = $db_obj->run_sql_with_exit_on_error($sql);
    $result_array = $db_obj->fetch_array_assoc($rs_data);
    foreach($result_array as $row) {
      $display_order[$row['c_desc']] = $row['country'];
    }
  }

  // Build Client Side Query String --
  // THIS SECTION builds 'jquery' client side query string for bottom table, which is distinct from Page server side URL, which resets this URL only after a 'Submit' page refresh.
  //pl
  $pl_url_string = $plgroup = "";
  $plgroup = $_REQUEST['plgroup'];
  $URL_plgroup = str_replace(" ","_",$plgroup);
  $pl_url = '&plgroup='.$URL_plgroup."&pl=";
  foreach ( $_REQUEST['pl'] as $v ) { $pl_url_string .= $v.","; } $pl_url_string = substr($pl_url_string, 0, -1);

  $pl_url .= $pl_url_string;

  //org
  $URL_org = str_replace(" ","_",$_REQUEST['org']);
  if ( !isset($_REQUEST['locallevel']) ) { $URL_locallevel = "000"; } else { $URL_locallevel = $_REQUEST['locallevel']; } // not setting sometimes, though URL is per GSR standard
  $org_url = '&org='.$URL_org.'&country='.$_REQUEST['country']."&locallevel=".$URL_locallevel;

  //sg
  $sg_url = '&sg='; foreach ($_REQUEST['sg'] as $v ) { $sg_url .= $v.","; } $sg_url = substr($sg_url, 0, -1);

  //bs
  $bs_url = '&bs='; foreach ($_REQUEST['bs'] as $v ) { $bs_url .= $v.","; } $bs_url = substr($bs_url, 0, -1);

  // Re-generate top button selection descriptors to drop into iframe below.
  // these $donut... variables repopulate the Product Group selections that had been made on the donut chart prior to a page level refresh
  if ( isset($_REQUEST['donut_plg']) ) { // for some reason, $_REQUEST is not always displaying these two params, can look into it later...
    $donut_plgs = $_REQUEST['donut_plg'];
    $donut_pls = $_REQUEST['donut_pl'];
  }
  else {
    $_REQUEST['donut_plg'] = "000";
    $loc1 = $loc2 = "";
    $http_url = $_SERVER['HTTP_REFERER'];
    $loc1 = strpos($http_url, "&donut_plg="); $loc2 = strpos($http_url, "&donut_pl=");

    if ( $loc1 !== false ) { // URL does not have query string
      $loc1 = $loc1 +11;
      $donut_plgs = substr($http_url, $loc1, $loc2 - $loc1);
      $loc1 = strpos($http_url, "&donut_pl=") +10; $loc2 = strpos($http_url, "--end");
      $donut_pls = substr($http_url, $loc1, $loc2 - $loc1);
    }
    else { $donut_plgs = "000"; $donut_pls = ""; }
  }
  $donut_plgs = str_replace("+"," ",$donut_plgs);
  $donut_plgs = explode("_", $donut_plgs);
  $donut_pls = explode("_", $donut_pls);

  $selected_plgroup = $all_pl_num = $all_pl_desc = "";
  if ( $_REQUEST['plgroup'] != "000" ) {
    $selected_plgroup = $_REQUEST['plgroup'];
  }
  if ( $_REQUEST['pl'][0] != "000" ) {
    foreach( $_REQUEST['pl'] as $k=>$v ) { $picked_pl[] = $v; }
  }
  else {
    // slight difference in the two pl lists generated of all instruments (top menu listings vs inst_data_array) - three items missing: AMD (M90), AMD (M91), AMD (M92) - check into at some time.
    if ( $selected_plgroup != "" ) {
      foreach( $inst_select_obj->InstGroupArray[$selected_plgroup] AS $k=>$v ) { $picked_pl[] = $k; }
    }
    else {
      foreach( $inst_select_obj->inst_data_array AS $k=>$v ) { $picked_pl[] = $k; }
    }
  }
  if ( $selected_plgroup == "" ) { $selected_plgroup = "000"; }

  // these two vars provide the default list when needing to return menus to 'ALL Instruments'.
  foreach( $inst_select_obj->inst_data_array AS $k=>$v ) { $all_pl_num .= $k.","; $all_pl_desc .= $v.","; }
  $all_pl_num = substr($all_pl_num,0,-1);
  $all_pl_desc = substr($all_pl_desc,0,-1);
  $all_pl_ct = substr_count($all_pl_num, ",");

  foreach($_REQUEST['sg'] as $k=>$v) { $picked_sg[] = $v; }
  foreach($_REQUEST['bs'] as $k=>$v) { $picked_bs[] = $v; }

  // Create week start and end arrays using PHP as it is ISO standard, whereas jquery has no such consistent functionality.  Needed below when changing date slider settings.
  $di_first = date_create($_REQUEST["di_first"]);
  $di_last = date_create($_REQUEST["di_last"]);

  $daterange = new DatePeriod($di_first, new DateInterval('P1D'), $di_last);

  foreach($daterange as $date) {
    if ( $date->format("N") == 1 ) {   // creates array of year/weeks and the first date of that week
      date_add($date, date_interval_create_from_date_string('-7 days')); // add in prior week, in case of partial week at beginning of year coinciding with date slider begins
      $week_dates_start[$date->format("oW")] = $date->format("Y-m-d");
      date_add($date, date_interval_create_from_date_string('7 days')); // reversion
      $week_dates_start[$date->format("oW")] = $date->format("Y-m-d");
    }
    if ( $date->format("N") == 7 ) {   // creates array of year/weeks and the last date of that week -- again, jquery is undependable in this regard
      $week_dates_end[$date->format("oW")] = $date->format("Y-m-d");
    }
  }
  $week_dates_end[$date->format("oW")] = $date->format("m/d/Y");

  // DERIVE TIME PERIODS
  // MINIMUM DATE
  $min_array = array();
  $min_array = normalize_date_min($_REQUEST["di_min"]);
  $norm_min = $min_array[0];
  $di_int = $min_array[1];

  function normalize_date_min($min_type) {  // year 2018
    //$min_type = $_REQUEST["di_min"]; // consistent across app, base date interval on format of 'di_min', regardless of what 'di_int' variable claims
    // di_min, if 8 digits without dashes, as in 'day', comes with dashes regardless of whether added in URL or not.
    if ( strlen($min_type) == 4 ) {
      $norm_min = $min_type."0101";
      $di_int = "y";
    }
    if ( stripos($min_type, 'q') !== false ) {  // quarter 2018Q1
      $q_min = substr($min_type,5,1);
      if ( $q_min == 1 ) { $m_min = "01"; } elseif ( $q_min == 2 ) { $m_min = "04"; } elseif ( $q_min == 3 ) { $m_min = "07"; } else { $m_min = "10"; }
      $norm_min = substr($min_type,0,4)."".$m_min."01";
      $di_int = "q";
    }
    if ( strlen($min_type) == 6 && stripos($min_type, 'q') === false ) {  // month  201804
      $norm_min = substr($min_type,0,4)."".substr($min_type,4,2)."01";
      $di_int = "m";
    }
    if ( stripos($min_type, 'w') !== false ) {  // week  2018w42
      $gendate = new DateTime();
      $gendate->setISODate(substr($min_type,0,4),substr($min_type,5,2),1);
      $norm_min = $gendate->format('Ymd');
      $di_int = "w";
    }
    if ( stripos($min_type, '-') !== false || ( strlen($min_type) == 8 && strpos($min_type, "-") === false ) ) {  // day
      if ( strpos($min_type, "-") === false ) {
        $norm_min = $min_type;
        //$norm_min = substr($min_type,0,4)."-".substr($min_type,4,2)."-".substr($min_type,6,2);
      }
      else {
        $norm_min = str_replace("-","",$min_type);
      }
      $di_int = "d";
    }
    return array($norm_min, $di_int);
  }

  // MAXIMUM DATE
  $norm_max = normalize_date_max($_REQUEST["di_max"]);

  function normalize_date_max($max_type) {
    //$max_type = $_REQUEST["di_max"];
    if ( strlen($max_type) == 4 ) {
      $norm_max = $max_type."1231";
    } // year 2018
    if ( stripos($max_type, 'q') !== false ) {  // quarter 2018Q1
      $q_max = substr($max_type,5,1);
      if ( $q_max == 1 ) { $q_max = "03"; } elseif ( $q_max == 2 ) { $q_max = "06"; } elseif ( $q_max == 3 ) { $q_max = "09"; } else { $q_max = "12"; }
      if ( $q_max == "03" || $q_max == "12" ) { $q_day = "31"; } else { $q_day = "30"; }
      $norm_max = substr($max_type,0,4)."".$q_max."".$q_day;
    }
    if ( strlen($max_type) == 6 && stripos($max_type, 'q') === false ) {  // month  201804
      $y_max = substr($max_type,0,4);
      $m_max = substr($max_type,4,2);
      if ( $m_max == "04" || $m_max == "06" || $m_max == "09" || $m_max == "11" ) { $d_max = 30; } elseif ( $m_max == "02" ) { $d_max = 28; } else { $d_max = 31; }
      $time = strtotime($m_max.'/01/'.$y_max);
      $thisy = date('Y',$time); $thisy = $thisy % 4;
      if ( $thisy == 0 && $m_max == "02" ) { $d_max = 29; }
      $norm_max = $y_max."".$m_max."".$d_max;
    }
    if ( stripos($max_type, 'w') !== false ) {  // week  2018w42
      $gendate = new DateTime();
      $gendate->setISODate(substr($max_type,0,4),substr($max_type,5,2),7);
      $norm_max = $gendate->format('Ymd');
    }
    if ( stripos($max_type, '-') !== false || ( strlen($max_type) == 8 && strpos($max_type, "-") === false ) ) {  // day
      if ( strpos($max_type, "-") === false ) {
        $norm_max = $max_type;
      }
      else {
        $norm_max = str_replace("-","",$max_type);
      }
    }
    return $norm_max;
  }

  function period_array($period_in, $period_to_shift_in, $date_interval_in) {
    if (is_null($period_in) OR is_null($period_to_shift_in) OR is_null($date_interval_in)) { return NULL; }

    $year = substr($period_in, 0, 4);
    $month = substr($period_in, 4, 2);
    $day = substr($period_in, 6, 2);

    if ( $date_interval_in == "y" ) { $new_period = date('Ymd', mktime(1, 1, 1, 1, $month, ($year + $period_to_shift_in))); }
    elseif ( $date_interval_in == "q" ) { $period_to_shift_in = $period_to_shift_in *3; $new_period = date('Ymd', mktime(1, 1, 1, ($month + $period_to_shift_in), 1, $year)); }
    elseif ( $date_interval_in == "m" ) { $new_period = date('Ymd', mktime(1, 1, 1, ($month + $period_to_shift_in), 1, $year)); }
    elseif ( $date_interval_in == "w" ) { $period_to_shift_in = $period_to_shift_in *7; $new_period = date('Ymd', mktime(1, 1, 1, $month, ($day + $period_to_shift_in), $year)); }
    elseif ( $date_interval_in == "d" ) { $new_period = date('Ymd', mktime(1, 1, 1, $month, ($day +1), $year)); }
    return $new_period;
  }

  $current_period = date('Ymd');
  $DEFAULT_MINPERIOD = period_array($current_period, -12, $di_int);
  $di_min = $minperiod = ( isset($_REQUEST["di_min"]) )  ? period_array($norm_min, 0, $di_int) : $DEFAULT_MINPERIOD;
  $DEFAULT_MAXPERIOD = period_array($current_period, -1, $di_int);
  $di_max = $maxperiod = ( isset($_REQUEST["di_max"]) )  ? period_array($norm_max, 0, $di_int): $DEFAULT_MAXPERIOD;

  $minperiod_array = array();

  $minperiod_array[$minperiod] = $minperiod;
  $next_period = $minperiod;
  do {
    $next_period = period_array($next_period, 1, $di_int);
    $minperiod_array[$next_period] = $next_period;
  } while ( $next_period < $maxperiod );

  function recast_interval($v,$di_int) {
    $year = substr($v, 0, 4);
    $month = substr($v, 4, 2);
    $day = substr($v, 6, 2);

    if ( $di_int == "y" ) { $recast = $year; }
    elseif ( $di_int == "q" ) {
      if ( $month <= 3 ) { $qNum = 1; } elseif ( $month <= 6 ) { $qNum = 2; } elseif ( $month <= 9 ) { $qNum = 3; } else { $qNum = 4; }
      $recast = $year."Q".$qNum;
    }
    elseif ( $di_int == "m" ) { $recast = $year."".$month; }
    elseif ( $di_int == "w" ) {
      $objDT = new DateTime($v); $yearNum = $objDT->format('o'); $weekNum = $objDT->format('W');
      $recast = $yearNum."W".$weekNum;
    }
    elseif ( $di_int == "d" ) { $recast = $year."-".$month."-".$day; }
    return $recast;
  }

  foreach( $minperiod_array AS $k=>$v ) {
    $recast = recast_interval($v,$di_int);
    $minperiod_array[$k] = $recast;
  }

  $DEFAULT_MINPERIOD = recast_interval($DEFAULT_MINPERIOD,$di_int);
  $DEFAULT_MAXPERIOD = recast_interval($DEFAULT_MAXPERIOD,$di_int);
  if ( ! in_array($minperiod, $minperiod_array ) ) { $minperiod = $DEFAULT_MINPERIOD; }
  if ( ! in_array($maxperiod, $minperiod_array ) ) { $maxperiod = $DEFAULT_MAXPERIOD; }

  $serv_perf_obj = new serv_perf($db_obj, $org_obj, $inst_select_obj,$sg_obj,$_REQUEST, $bs_obj, $di_obj, true);

  $json_data =  $serv_perf_obj->get_data();
  $data = json_decode($json_data);

  if ( $report_type == "serv_perf" ) {
    if ( $sub_type == "ind" ) { // by "ctry" is currently set above
      // Get list of individuals by login for selected.
      $display_order = array();
      foreach( $data->login_mapping as $k=>$v ) {
        if ( $v == "" || $v == "Suppressed" ) { $v = $k; }
        $display_order[$v] = $k;
      }

      $cc_selected = $cc = "000";
    }
    else if ( $sub_type == "cntr" ) {
      // Get list of call centers for selected.
      // grabs 'Centers' output of get_centers.php file
      ob_start(); //Start output buffer
      include "get_centers.php";
      $centers = ob_get_contents(); //Grab output
      ob_end_clean(); //Discard output buffer
      $centers = json_decode($centers);

      $display_order = array();
      $i = 0;
      foreach( $centers as $row ) {
        $display_order[] = $row[0];
      }

      if ( count($display_order) > 1 ) { $cc_selected = "000"; }
      elseif ( count($display_order) == 1 ) {
        $start = strpos($display_order[0],"::") +2;
        $disp = substr($display_order[0],$start);

        $cc_selected = $cc = $display_order[0];
      }
      else { $cc_selected = ""; }
    }
    else {
      $cc_selected = $country_selected;
    }
  }
  else {
    $cc_selected = $country_selected;
  }

  $stacked_display_order = array(); // set this regardless of whether it contains data

  if ( $report_type == "prr_ex" ) {
    // Get list of errors for selected.
    $display_order = array();
    if ( isset($data->codes_exp_code) ) {
      foreach( $data->codes_exp_code as $k=>$v ) {
      $display_order[$k] = $k;
      }
    }
    if ( $sub_type == "ind" ) { // by "ctry" is currently set above
      // Get list of individuals by login for selected.
      if ( isset($data->login_mapping) ) {
        foreach( $data->login_mapping as $k=>$v ) {
        if ( $v == "" || $v == "Suppressed" ) { $v = $k; }
        $stacked_display_order[$v] = $k;
        }
      }
    }
    if ( $sub_type == "cntr" ) {
      // Get list of call centers for selected.
      if ( isset($data->total_centers->total_calls) ) {
        foreach( $data->total_centers->total_calls as $k=>$v ) {
        $stacked_display_order[] = $k;
        }
      }
    }
  }

  $i=0;
  foreach( $inst_select_obj->inst_data_array as $k=>$v ) {
    $inst_data_sorted[$i] = $v;
    $i++;
  }
  $i=0;
  foreach( $org_obj->countries_array as $k=>$v ) {
    $org_obj->countries_array_sorted->k[$i] = $k;
    $org_obj->countries_array_sorted->v[$i] = $v;
    $i++;
  }

  // Get list of areas
  $sql = "SELECT distinct area FROM reliability.dbo.country";
  $rs_data = $db_obj->run_sql_with_exit_on_error($sql);
  $result_array = $db_obj->fetch_array_assoc($rs_data);
  foreach($result_array as $row) {
    $areas_orgs[] = $row['area'];
  }

?>

<html>
<head>
  <script type="text/javascript" src="/gsr/common/javascript/jquery-1.11.3.min.js"></script>  <!-- v1.11.3 -->
  <script type="text/javascript" src="javascript/jquery-ui1.12.1.min.js"></script>

  <!-- chart.js -->
  <script type="text/javascript" src="javascript/Chart.bundle.min2.7.3.js"></script>

  <!-- datatables -->
  <link rel="stylesheet" type="text/css" href="css/datatables1.10.18.min.css"/>
  <script type="text/javascript" src="javascript/datatables1.10.18.min.js" defer></script> <!-- 'defer' needed due to conflict in jquery -->

  <link href="grid.css" rel="stylesheet" type="text/css" />
  <link href="call-center.css" rel="stylesheet" type="text/css" />
  <!--<link href="css/google_fonts.css" rel="stylesheet">-->
</head>
<body>


<form id="jquery_data" class="jquery_data">
  <!-- bucket for hidden jquery field data  - to maintain state between changes (client side) or page level (server side) refreshes
  id=period-idx|val  for tracking individual period selections in trendchart, kept blank otherwise
  //id=period-norm...  for tracking min and max date settings at all times --
  id=prior|current*  track which donut slice (pl) is highlighted, on page reset it rehighlights selection
  id=topmenu*   track so onclick of changes in top chart, resets occur properly
  id=pls*   pls list, selected by 'page' or 'frame' method, for queries, bottom list, etc., depending on where clicked
  id=plg-p   product group selected currently in top menu
  id=plgs-f   all product groups selected currently in donut chart
  -->
  <div class="h-cc">
  <input type="hidden" id="cc" name="cc" value="<?php echo $cc_selected; ?>">
  </div>
  <div class="h-per">
  <input type="hidden" id="period-idx" name="period-idx" value="">
  <input type="hidden" id="period-val" name="period-val" value="">
  <input type="hidden" id="period-min" name="period-min" value="<?php echo $_REQUEST['di_min']; ?>">
  <input type="hidden" id="period-max" name="period-max" value="<?php echo $_REQUEST['di_max']; ?>">
  </div>
  <div class="h-slice">
  <input type="hidden" id="prior-slice-col" name="prior-slice-col" value="">
  <input type="hidden" id="prior-slice-idx" name="prior-slice-idx" value="">
  <input type="hidden" id="current-slice-pl" name="current-slice-pl" value="">
  <input type="hidden" id="extra-slices-list" name="extra-slices-list" value="">
  </div>
  <div class="h-top">
  <input type="hidden" id="topmenu-org" name="topmenu-org" value="<?php echo $_GET['org']; ?>">
  <input type="hidden" id="topmenu-country" name="topmenu-country" value="<?php echo $_GET['country']; ?>">
  <input type="hidden" id="topmenu-locallevel" name="topmenu-locallevel" value="<?php echo $_GET['locallevel']; ?>">
  </div>
  <div class="h-pl-n">
  <input type="hidden" id="pls-nums-p" name="pls-nums-p" value="">
  <input type="hidden" id="pls-nums-f" name="pls-nums-f" value="">
  </div>
  <div class="h-pl-d">
  <input type="hidden" id="pls-desc-p" name="pls-desc-p" value="">
  <input type="hidden" id="pls-desc-f" name="pls-desc-f" value="">
  </div>
  <div class="h-plg">
  <input type="hidden" id="plg-p" name="plg-p" value="<?php echo $selected_plgroup; ?>">
  <input type="hidden" id="plgs-f" name="plgs-f" value="">
  </div>
  <div class="h-all-pl">
  <input type="hidden" id="all-pl-num" name="all-pl-num" value="<?php echo $all_pl_num; ?>">
  <input type="hidden" id="all-pl-desc" name="all-pl-desc" value="<?php echo $all_pl_desc; ?>">
  <input type="hidden" id="all-plgs" name="all-plgs" value="">
  </div>
  <div class="stacked-chart">
  <input type="hidden" id="stacked-scope" name="stacked-scope" value="">
  <input type="hidden" id="stacked-errs" name="stacked-errs" value="">
  <input type="hidden" id="stacked-orgs" name="stacked-orgs" value="">
  <input type="hidden" id="stacked-sums" name="stacked-sums" value="">
  <input type="hidden" id="stacked-pcts" name="stacked-pcts" value="">
  </div>
  <div class="for-csv">
  <!-- bottom table dropped in here for use in exporting csv -->
  </div>
  <div id="sql_data" style="display: none !important;" value=""> <!-- needed here to preserve data when regenerating prr_ex stacked table -->
  <?php echo htmlentities( json_encode( $data ) ); ?>
  </div>
</form>
<div id="content-wrap" class="content-wrap">
<!--
//
//   ROW 0
//
-->
<div class="l-wrap">
  <div class="l-row l-row-0">
  <div class="grid-item l-size-100">
    <div class="grid-inner">
  <div id="summation" class="summation">
  <!-- description of selections is dropped into here -->
  </div>

    </div>
  </div>
  </div>
</div>
<!--
//
//   Row 1
//
-->
<div class="l-wrap">
  <div class="l-row l-row-1">
  <div class="grid-item l-size-75">
    <div id="tt_re" class="grid-inner">
  <!-- Top DataTable goes here -->
    </div>
  </div>
  <div class="grid-item l-size-25">
    <div class="grid-inner">
  <?php if ( $_REQUEST["report_type"] != "prr_ex" ) { ?>
  <div class="top-meter meter-img-outer-wrapper">
  <div class="meter-img-inner-wrapper">
  <img class="PRR-meter" >
  </div>
  </div>
  <div class="bottom-meter meter-img-outer-wrapper">
  <div class="meter-img-inner-wrapper">
  <img class="PPRR-meter" >
  </div>
  </div>
  <?php ;}
  else { ?>
  <style>
  .l-row .l-size-25 {
    width: 0;
    float: none;
  }
  .l-row .l-size-75 {
    width: 100%;
  }
  #tt_wrapper td.limit-width {
    width: 33% !important;
  }
  .dataTables_scroll thead th:first-child {
    width: 33% !important;
  }
  </style>
  <?php ;} ?>
    </div>
  </div>
  </div>
</div>  <!-- end l-wrap row 1 -->
<!--
//
//   Row 2
//
-->
<div class="l-wrap">
  <div class="l-row l-row-2">

  <?php
  $product_group_select = $plgs_f = "";

  if ( $_REQUEST["report_type"] != "prr_ex" ) { ?>

  <div class="grid-item l-size-40">
  <div class="grid-inner">
  <div class="above-result">
  <div id="all-trend" class="all-trend">Return to All Periods</div>
  <div id="all-trend-IE-is-special" class="all-trend-IE"></div>
  <div id="chart-container" class="chart-container" style="/*position: relative; height:32%; width:100%;*/">
  <!-- make changes to proportions here only, as canvas responsiveness works by altering bounding div -->
  <canvas id="trendChart"></canvas>  <!-- div must not contain anything other than this canvas call -->
  </div>
  <!-- <div id="result"> </div>  -->
  </div>
  </div>
  </div>
  <?php
  // Donut Chart Checkbox Array
  $ogsel = array();

  foreach($inst_select_obj->InstGroupArray as $k=>$v){
    $plgs_f .= $k.",";
    if ( $k != "Undetermined" && $k != "Other" ) { $pgsel[] = $k; }
  }
  sort($pgsel);
  foreach($pgsel as $k=>$v){
    $product_group_select .= "<div class=\"pgsel-checkbox\"><input type=\"checkbox\" name=\"prod_group\" value=\"$v\"> $v</div>";
  }
  $plgs_f = substr($plgs_f,0,-1);
  ?>
  <script>
  $("#jquery_data #all-plgs").remove();
  $("#jquery_data .h-all-pl").append('<input type="hidden" id="all-plgs" name="all-plgs" value="<?php echo $plgs_f; ?>">');
  </script>
  <?php

  ?>
  <div class="grid-item l-size-60">
  <div class="grid-inner">
  <div class="above-result2">
  <div id="donut-left" class="donut-left">

  <div class="donut-left-title">Product Group</div>
  <form id="donut-pgsel" class="donut-pgsel">
  <div class="pgsel-checkbox pgsel-checkbox-all" style="float: left;"><input type="checkbox" name="prod_group" value="all" class="pgsel_check-all"> ALL</div>
  <div class="pgsel-checkbox pgsel-checkbox-none"><input type="checkbox" name="prod_group" value="none" class="pgsel_check-none"> NONE</div>
  <?php echo $product_group_select ?>
  <div class="pgsel-checkbox"><input type="checkbox" name="prod_group" value="Undetermined"> Undetermined</div>
  <div class="pgsel-checkbox"><input type="checkbox" name="prod_group" value="Other"> Other</div>
  <div id="donut-checksum"></div>
  </form>

  </div>
  <span class="no-results"></span>

  <div id="donut-right" class="donut-right">
  <div id="result2">
  <!-- make changes to proportions here only, as canvas responsiveness works by altering bounding div -->
  <canvas id="donutChart" width="500" height="300"></canvas>  <!-- div must not contain anything other than this canvas call -->
  </div>


  <div class="donut-right-bottom">
  <div id="all-donut" class="all-donut">Return to All Products</div>
  <div id="donut-note" class="donut-note">For multiple Product selection,<br/>use Top Menu Instruments selector.</div>
  </div>
  </div>
  </div>
  </div>
  </div>

  <?php ;}
  else { ?>

    <div class="grid-item l-size-100">
    <div class="grid-inner">
    <div id="stacked_scroll">
    <?php if ( $report_type == "prr_ex" && $sub_type == "ctry" ) { ?>
      <div id="stacked-selector">
      <div id="stacked-selector1" class="stacked-selectors">Areas</div>
      <div id="stacked-selector2" class="stacked-selectors">Regions</div>
      <div id="stacked-selector3" class="stacked-selectors">Countries</div>
      </div>
    <?php } ?>
    <div id="stacked_prr" class="stacked_prr" style="display: block; width: 1650px; height: 286px;">
    <canvas id="stackedChart" width="1650" height="286"></canvas>
    </div>
    </div>
    </div>
    </div>

  <?php ;} ?>

  </div>
</div>  <!-- end l-wrap row 2 -->
<!--
//
//   Row 3
//
-->
<div class="l-wrap">
  <div class="l-row l-row-3">
  <div class="grid-item l-size-100">
    <div id="bt_re" class="grid-inner">
  <!-- Bottom DataTable goes here -->
    </div>
  </div>
  </div>
</div>  <!-- end l-wrap row 3 -->
<!--
//
//   Row 4
//
-->
<div class="l-wrap l-wrap-4">
  <div class="l-row l-row-4">
  <div class="grid-item l-size-100">
    <div class="grid-inner">
  <div id="detailed_summation" class="detailed_summation">
  <span class="call_center_footer_button">Click Here to Show/Hide Additional Selection Criteria Details</span>
  <BR/>
  <div tabindex="0" class="call_center_footer">
    <ul class="footer_content">
  <?php
  $pl_nums = $pls_footer = $sg_footer = $bs_footer = "";
  foreach ( $picked_pl as $k=>$v ) {
    $pl_nums .= $v.",";
    if ( $v == "000" ) { $w = "All Instruments"; }
    else { $w = $inst_select_obj->inst_data_array[$v];  }
    $pls_footer .= $w.", ";
  };
  $pls_footer = substr($pls_footer, 0, -2);
  $pl_nums = substr($pl_nums, 0, -1);

  ?>
  <script>
  var is_pls_desc = ""; var is_pls_num = "";
  $("#jquery_data #pls-desc-p").remove();
  $("#jquery_data .h-pl-d").append('<input type="hidden" id="pls-desc-p" name="pls-desc-p" value="<?php echo $pls_footer; ?>">');
  is_pls_desc += $("#jquery_data .h-pl-d #pls-desc-f").val();
  if ( is_pls_desc == "" ) {
    $("#jquery_data #pls-desc-f").remove();
    $("#jquery_data .h-pl-d").append('<input type="hidden" id="pls-desc-f" name="pls-desc-f" value="<?php echo $pls_footer; ?>">');
  }

  $("#jquery_data #pls-nums-p").remove();
  $("#jquery_data .h-pl-n").append('<input type="hidden" id="pls-nums-p" name="pls-nums-p" value="<?php echo $pl_nums; ?>">');
  is_pls_num += $("#jquery_data .h-pl-n #pls-nums-f").val();
  if ( is_pls_num == "" ) {
    $("#jquery_data #pls-nums-f").remove();
    $("#jquery_data .h-pl-n").append('<input type="hidden" id="pls-nums-f" name="pls-nums-f" value="<?php echo $pl_nums; ?>">');
  }

  $("#jquery_data #plgs-f").remove();
  var donut_plg = "<?php echo $_REQUEST['donut_plg']; ?>";

  donut_plg = donut_plg.replace(/\_/g, ',');
  if ( donut_plg == "" || donut_plg == "000" ) {
    $("#jquery_data .h-plg").append('<input type="hidden" id="plgs-f" name="plgs-f" value="<?php echo $plgs_f; ?>">');
  }
  else {
    $("#jquery_data .h-plg").append('<input type="hidden" id="plgs-f" name="plgs-f" value="'+donut_plg+'">');
  }
  var plgs_f = $("#jquery_data .h-plg #plgs-f").val();
  var plgs_f_arr = plgs_f.split(",");
  $.each( plgs_f_arr, function(i, n) {
    $(".pgsel-checkbox input[value='"+n+"']").attr("checked","checked");
    $(".pgsel-checkbox input[value='"+n+"']").prop("checked",true);
  });
  </script>
  <?php

  foreach ( $picked_sg as $k=>$v ) {
    if ( $v == "000" ) { $v = "All Servicing Groups"; }
    $sg_footer .= $v.", ";
  };
  $sg_footer = substr($sg_footer, 0, -2);

  foreach ( $picked_bs as $k=>$v ) {
    if ( $v == "000" ) { $w = "All Business Segments"; }
    else if ( $v == 1 ) { $w = "AMD"; }
    else if ( $v == 2 ) { $w = "Core Lab"; }
    else if ( $v == 4 ) { $w = "Transfusion"; }
    else if ( $v == 99 ) { $w = "Unknown"; }
    else { $w = ""; }
    $bs_footer .= $w.", ";
  };
  $bs_footer = substr($bs_footer, 0, -2);

  echo "<b>Instruments Selected:</b> ".$pls_footer."<br/><br/><b>Servicing Groups Selected:</b> ".$sg_footer."<br/><br/><b>Business Segments Selected:</b> ".$bs_footer;
  ?>
    </ul>
  </div>
  <BR/>
  </div>
    </div>
  </div>
  </div>
</div>
</div>  <!-- end content wrap -->


<script type="text/javascript">
   // Grab the request data as we will be using it for the bottom table.
  var request = <?php echo json_encode($_REQUEST); ?>;

  var orig_country_request = request.country;
  var orig_ind_request = request.ind_hand;
  var orig_center_request = request.centers;

  var sub_type  = "<?php echo $sub_type; ?>";
  var report_type  = "<?php echo $report_type; ?>";

  var data = <?php echo json_encode($data, JSON_PRETTY_PRINT); ?>;
  var per = <?php echo json_encode($minperiod_array, JSON_PRETTY_PRINT); ?>;
  var inst_data = <?php echo json_encode($inst_select_obj->inst_data_array, JSON_PRETTY_PRINT); ?>;
  var inst_data_sorted = <?php echo json_encode($inst_data_sorted, JSON_PRETTY_PRINT); ?>;
  var inst_group_data = <?php echo json_encode($inst_select_obj->InstGroupArray, JSON_PRETTY_PRINT); ?>;
  var display_order = <?php echo json_encode($display_order, JSON_PRETTY_PRINT); ?>;
  var stacked_display_order = <?php echo json_encode($stacked_display_order, JSON_PRETTY_PRINT); ?>;
  var org_data = <?php echo json_encode($org_data, JSON_PRETTY_PRINT); ?>;
  var org_obj = <?php echo json_encode($org_obj, JSON_PRETTY_PRINT); ?>;
  var donut_plgs = <?php echo json_encode($donut_plgs, JSON_PRETTY_PRINT); ?>;
  var donut_pls = <?php echo json_encode($donut_pls, JSON_PRETTY_PRINT); ?>;
  var picked_sg = <?php echo json_encode($picked_sg, JSON_PRETTY_PRINT); ?>;
  var picked_bs = <?php echo json_encode($picked_bs, JSON_PRETTY_PRINT); ?>;
  var week_dates_start = <?php echo json_encode($week_dates_start, JSON_PRETTY_PRINT); ?>;
  var week_dates_end = <?php echo json_encode($week_dates_end, JSON_PRETTY_PRINT); ?>;

  var minperiod = "<?php echo $minperiod ?>";
  var maxperiod = "<?php echo $maxperiod ?>";

  var page_refresh = "<?php echo $page_refresh ?>";

  var top_menu_button_desc_instr = " ";
  var top_menu_button_desc_org = " ";

  var notInit = 0;
  var once_is_enough = 0;
  var is_IE = "<?php echo $is_IE ?>";

  var org_sql_url = org_data.org_sql.replace(/\', '/g, "','");
  org_sql_url = org_sql_url.replace(/\ /g, '_');

  var org_url = "<?php echo $org_url; ?>"+"&org_sql="+org_sql_url;

  var urlstring = "<?php echo $pl_url; ?>"+org_url+"<?php echo $sg_url.$bs_url; ?>";

  var areas_orgs = <?php echo json_encode($areas_orgs, JSON_PRETTY_PRINT); ?>;
  regions_orgs = [];
  $.each( org_obj.all_orgs_array, function(i, v) {
    if ( org_obj.all_orgs_array[i]["org_prefix_id"] == 3 ) { regions_orgs.push(org_obj.all_orgs_array[i]["org_name"]); }
  });


  // AFTER PAGE REFRESH
  if (donut_plgs[0] == "000" ) {
    $(".pgsel-checkbox input").attr("checked","checked");
    $(".pgsel-checkbox input").prop("checked",true);
    $(".pgsel_check-all").removeAttr("checked");
    $(".pgsel_check-none").removeAttr("checked");
  }
  else {   // other code takes care of checking the correct box
  }


  // ONCLICK OF TOP CHART  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @
  $("#tt_re").on("click", "td:first-child", function() {

    var source = "top";
    once_is_enough = 0;

    var minperiod = $('#jquery_data #period-min').val();
    var maxperiod = $('#jquery_data #period-max').val();

    // Get selected top table row
    if ( report_type == "serv_perf" ) {
      if ( $(this).attr("class") == "ui-state-default limit-width" ) { var cc = "000"; }
      else { var cc = $(this).closest("tr").data("cc"); }
      c_code_bottom_table = cc;
    }
    else {
      if ( $(this).attr("class") == "ui-state-default limit-width" ) { var cc = "000"; }
      else { var cc = $(this).closest("tr").data("exp"); }
      e_code_bottom_table = cc;
    }

    // UPDATE METERS
    $('.PRR-meter').html(updateMeter1(cc));
    $('.PPRR-meter').html(updateMeter2(cc));

    // Top Table Highlighting
    if ( report_type == "serv_perf" ) {
      $("#tt td").removeClass("selected-cc");
      $(".dataTables_scrollFoot td").removeClass("selected-cc");
      $(this).addClass("selected-cc");
    }
    else {
      $("#tt td").removeClass("selected-exp");
      $(".dataTables_scrollFoot td").removeClass("selected-exp");
      $(this).addClass("selected-exp");
    }
    $("#tt tr").removeClass("selected-row");
    $(".dataTables_scrollFoot td:first-child").css("color","white");
    $(this).css("color","black");
    $(this).parents("tr").addClass("selected-row");


    // UPDATE TREND CHART  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    if ( report_type == "serv_perf" ) {
      $("#jquery_data #cc").remove();
      $("#jquery_data .h-cc").append('<input type="hidden" id="cc" name="cc" value="'+cc+'">');

      var period_idx = $('#jquery_data #period-idx').val();

      // GRAB ALL PLs FOR CHECKED PRODUCT GROUPS
      checked_pl_groups = []; checked_pls = [];

      var i = 0; $.each(inst_group_data, function(v,w){ i++; });   // ".length" did not work on counting just the top level Prod Group headings
      // get checked Product Groups
      var j = 0, endit = 0, checked_pl_groups_all = 0, pl_groups_list = "";

      pl_groups_list += $("#jquery_data #plgs-f").val();
      j = (pl_groups_list.match(/,/g) || []).length;

      if ( pl_groups_list.length === 0 ) { endit = 1; }
      else if ( j == i-1 || pl_groups_list == "000" ) { checked_pl_groups_all = 1; }

      // See if a single PL is highlighted first in donut chart
      var hilite_pl = "";
      hilite_pl = $("#jquery_data #current-slice-pl").val();
      checked_pl = [];
      if ( hilite_pl != "" ) {
        checked_pl.push(hilite_pl);
      }

      // get PLs
      var pls_list = "", pls_footer = "", j0 = "", fail0 = 0;

      pls_list = $("#jquery_data #pls-nums-f").val();
      pls_footer = $("#jquery_data #pls-desc-f").val();
      checked_pls = pls_list.split(",");

      buildTrendChart(source,checked_pl_groups_all,notInit,cc,per,period_idx);


      // UPDATE DONUT CHART   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
      buildDonutChart(source,cc,checked_pls);

      // Get new chart data, grab last background color entry -- but only if it is rgba (deliberately saved as such), store in jquery table, indicating color of slice when not highlighted.
      var i = donutChart.config.data.datasets[0].backgroundColor.length;
      i--;
      // save new slice color
      var save_slice_col = donutChart.config.data.datasets[0].backgroundColor[i];
      $("#jquery_data #prior-slice-col").remove();
      $("#jquery_data .h-slice").append('<input type="hidden" id="prior-slice-col" name="prior-slice-col" value="'+save_slice_col+'">');

      // UPDATE TOP MENU ORGANIZATIONS SELECTOR
      if ( report_type == "serv_perf" && sub_type == "ctry" ) {
        // deselect all organizations
        $("#org option", window.parent.document).attr("selected", false);
        $("#country option", window.parent.document).attr("selected", false);
        $("#locallevel option", window.parent.document).attr("selected", false);

        // remove all existing options at 'Locations' (country) and 'LocalLevels' levels, as they are changed out depending on which selections made in 'Organizations' (org)
        $("#country option", window.parent.document).remove();
        $("#locallevel option", window.parent.document).remove();
        // add options back in
        $("#country", window.parent.document).append($("<option></option>").attr("value","000").text("Use Organization Above"));
        $("#locallevel", window.parent.document).append($("<option></option>").attr("value","000").text("Use Location Above"));

        var topmenu_org = $('#jquery_data #topmenu-org').val();
        var topmenu_country = $('#jquery_data #topmenu-country').val();

        var cc_desc = $("#tt tbody").find('tr[data-cc='+cc+']').find(".td-ctry").text();
        // where is the selected country
        var country_in = "", country = "", CountryDesc = "", locallevel = "";

        $.each(areas_orgs, function(i,v){  //  v = AMT
          $.each(org_obj.org_countries_array, function(j,w){
            if ( org_obj.org_countries_array[j]['org_name'] == v ) {
              if ( org_obj.org_countries_array[j]['country'] == cc ) { country_in = org_obj.org_countries_array[j]['org_name']; }
            }
          });
        });
        if ( country_in == "" ) { country_in = "WorldWide"; } // in cases where Total (meaning all entities) is selected

        // Fill relevant locations
        $.each(org_obj.org_countries_array, function(i, v){
          if ( org_obj.org_countries_array[i]['org_name'] == country_in || country_in == "WorldWide" ) {
            country = org_obj.org_countries_array[i]['country'];
            CountryDesc = org_obj.org_countries_array[i]['CountryDesc'];
            $("#country", window.parent.document).append($("<option></option>").attr("value",country).text(CountryDesc));
          }
        });
        // Fill relevant locallevels
        $.each(org_obj.locallevel_array, function(i, v){
          if ( org_obj.locallevel_array[i]['country'] == cc ) {
            locallevel = org_obj.locallevel_array[i]['locallevel'];
            $("#locallevel", window.parent.document).append($("<option></option>").attr("value",locallevel).text(locallevel));
          }
        });

        // select org, location, locallevel
        if ( cc == "000" ) {
          $('#org option[value="'+topmenu_org+'"]', window.parent.document).prop({defaultSelected: true});  // set both
          $('#org option[value="'+topmenu_org+'"]', window.parent.document).prop("selected", "selected");
          $('#country option[value="'+topmenu_country+'"]', window.parent.document).prop({defaultSelected: true});
          $('#country option[value="'+topmenu_country+'"]', window.parent.document).prop("selected", "selected");
          $('#locallevel option[value="000"]', window.parent.document).prop({defaultSelected: true});
        }
        else {
          $("#org option[value=\""+country_in+"\"]", window.parent.document).prop({defaultSelected: true});  // set both
          $("#org option[value=\""+country_in+"\"]", window.parent.document).prop("selected", "selected");
          $("#country option[value=\""+cc+"\"]", window.parent.document).prop({defaultSelected: true});
          $("#country option[value=\""+cc+"\"]", window.parent.document).prop("selected", "selected");
          $('#locallevel option[value="000"]', window.parent.document).prop({defaultSelected: true});
        }
        // Update Top Menu Organizations button 'selected' text
        if ( cc == '000' ) { var org_button_title = topmenu_org; } else { var org_button_title = country_in+ "<br/>" +cc_desc; }
        $("#top_sel_orgs span", window.parent.document).html(org_button_title);

        // set Org descriptor
        if ( cc == '000' ) { org_data.org_desc = topmenu_org; } else { org_data.org_desc = cc_desc; }
        top_menu_button_desc_org = org_data.org_desc;
      }

      $('#summation').html(currentSummation);

      top_table_scroll_to(); // this location seems to not work for adjusting selected ctry

      // FOOTER
      if ( hilite_pl != "" ) {
        pls_footer = inst_data[checked_pl];
      };

    }

    // UPDATE BOTTOM TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    // when total in top table is selected, this prevents the bottom table from trying to show all 100k+ entries
    if ( report_type == "serv_perf" ) {
      // footer total selected?
      var found_selected = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr >  td.ui-state-default.limit-width.selected-cc").html();
      // footer total amount
      var this_is_the = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.td-all-totl").html();
    }
    else { // report_type = prr_ex
      var found_selected = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.selected-exp").html();
      var this_is_the = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.td-all-totl").html();
    }

    if ( typeof found_selected !== 'undefined' && this_is_the >= 10000 ) {   // threshold to display bottom table
      $("#bt_re").html("<div class=\"bottom-table-noselect-text\">please make a selection above</div>");
    }
    else {
      var bt = $( "#bt" ).DataTable();
      $("#bt_re").empty();
      bt.destroy();

      $('#bt_re').addClass("blank");

      if ( report_type == "prr_ex" ) {
        checked_pls = "";   // temp for now, may not need to set it here
        updateBottomTable(pl_groups_list, checked_pls, pls_list, e_code_bottom_table, "top_table_exp");
      }
      else {
        updateBottomTable(pl_groups_list, checked_pls, pls_list, c_code_bottom_table, "top_table");
      }

      jQuery("#bt_re").ready(checkContainer);
      jQuery("#bt_re").ready(checkContainer1);
      bottomChartCSV();
    }


    var sg_footer = "", bs_footer = "", w = "";
    buildFooter(pls_footer,sg_footer,bs_footer);
  });  // END -- ONCLICK OF TOP CHART


  // ONCLICK OF TREND CHART "RETURN TO ALL PERIODS" BUTTON  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @
  $("#all-trend").click(function() {

    var source = "trend";
    once_is_enough = 0;

    $("#jquery_data #period-idx").remove();
    $("#jquery_data #period-val").remove();
    $("#jquery_data #period-norm-val-min").remove();
    $("#jquery_data #period-norm-val-max").remove();
    $("#jquery_data .h-per").append('<input type="hidden" id="period-idx" name="period-idx" value="">');
    $("#jquery_data .h-per").append('<input type="hidden" id="period-val" name="period-val" value="">');

    var minperiod = "<?php echo $_REQUEST["di_min"]; ?>";
    var maxperiod = "<?php echo $_REQUEST["di_max"]; ?>";

    $("#jquery_data #period-min").remove();
    $("#jquery_data #period-max").remove();
    $("#jquery_data .h-per").append('<input type="hidden" id="period-min" name="period-min" value="'+minperiod+'">');
    $("#jquery_data .h-per").append('<input type="hidden" id="period-max" name="period-max" value="'+maxperiod+'">');

    var nmin = normalize_date_min("<?php echo $_REQUEST["di_min"]; ?>");
    var nmax = normalize_date_max("<?php echo $_REQUEST["di_max"]; ?>");

    // Update Top Menu Dates button 'selected' text
    if ( minperiod == maxperiod ) { top_menu_button_date = minperiod; } else { top_menu_button_date = minperiod+ " to " +maxperiod; }
    $("#date_interval span", window.parent.document).text(top_menu_button_date);

    $("#jquery_di_data #di_min", window.parent.document).remove();  // in format selected by user
    $("#jquery_di_data #di_max", window.parent.document).remove();
    $("#jquery_di_data", window.parent.document).append("<input type=\"hidden\" id=\"di_min\" name=\"di_min\" value=\"<?php echo $_REQUEST["di_min"]; ?>\">");
    $("#jquery_di_data", window.parent.document).append("<input type=\"hidden\" id=\"di_max\" name=\"di_max\" value=\"<?php echo $_REQUEST["di_max"]; ?>\">");

    $("#jquery_di_data #di_min_norm", window.parent.document).remove();  // for resetting slider positions
    $("#jquery_di_data #di_max_norm", window.parent.document).remove();
    $("#jquery_di_data", window.parent.document).append("<input type=\"hidden\" id=\"di_min_norm\" name=\"di_min_norm\" value=\""+nmin+"\">");
    $("#jquery_di_data", window.parent.document).append("<input type=\"hidden\" id=\"di_max_norm\" name=\"di_max_norm\" value=\""+nmax+"\">");

    // if Dates (rather than Dates & Intervals) top menu selector is on, set this
    $("#minperiod option:selected", window.parent.document).removeAttr("selected");
    $("#minperiod", window.parent.document).val(minperiod);
    $("#maxperiod option:selected", window.parent.document).removeAttr("selected");
    $("#maxperiod", window.parent.document).val(maxperiod);
    //top_sel_dates
    var top_min = minperiod.slice(0,4)+"/"+minperiod.slice(4);  //201808
    var top_max = maxperiod.slice(0,4)+"/"+maxperiod.slice(4);
    $("#top_sel_dates", window.parent.document).html("Dates<br/><span>"+top_min+" -> "+top_max+"</span>");
    notInit = 1; // once past initial page load, this triggers functions like 'destroy', in the case of the donut chart.
    $(".above-result #all-trend").css("visibility","hidden");

    $("#all-trend-IE-is-special").text("");

    top_table_scroll_to();

    createInitial();
    $('#summation').html(currentSummation);
  });


  // ONCLICK OF TREND CHART  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @
  function trendClick() {
    $("#trendChart").click(function(e) {

      var source = "trend";
      once_is_enough = 0;

      $(".above-result #all-trend").css("visibility","visible");

      var pointa = trendChart.getElementAtEvent(e);

      if (pointa[0]) {
        var period_idx = pointa[0]['_index'];
        var this_per = pointa[0]['_chart'].config.data.labels[period_idx];

        minperiod = this_per;
        maxperiod = this_per;
        // Set selected period index
        $("#jquery_data #period-idx").remove();
        $("#jquery_data .h-per").append('<input type="hidden" id="period-idx" name="period-idx" value="'+period_idx+'">');
        // Set selected period value
        $("#jquery_data #period-val").remove();
        $("#jquery_data .h-per").append('<input type="hidden" id="period-val" name="period-val" value="'+this_per+'">');
        $("#jquery_data #period-min").remove();
        $("#jquery_data #period-max").remove();
        $("#jquery_data .h-per").append('<input type="hidden" id="period-min" name="period-min" value="'+this_per+'">');
        $("#jquery_data .h-per").append('<input type="hidden" id="period-max" name="period-max" value="'+this_per+'">');
        var cc = $('#jquery_data #cc').val();

        // where is the selected country -- for bottom table URL query string
        var country_in = "", country = "", CountryDesc = "", locallevel = "";

        if ( cc != "000" ) {
          $.each(areas_orgs, function(i,v){  //  v = AMT
            $.each(org_obj.org_countries_array, function(j,w){
            if ( org_obj.org_countries_array[j]['org_name'] == v ) {
              if ( org_obj.org_countries_array[j]['country'] == cc ) { country_in = org_obj.org_countries_array[j]['org_name']; }
            }
            });
          });
        }
        else {
          country_in = "WorldWide";
        }

        // GRAB ALL PLs FOR CHECKED PRODUCT GROUPS
        var pl_groups_list = "";

        checked_pls = [];
        var i = 0; $.each(inst_group_data, function(v,w){ i++; });   // ".length" did not work on counting just the top level Prod Group headings
        // get checked Product Groups
        var j = 0, endit = 0, checked_pl_groups_all = 0, pl_groups_list = "";

        pl_groups_list += $("#jquery_data #plgs-f").val();
        j = (pl_groups_list.match(/,/g) || []).length +1;

        if ( pl_groups_list.length === 0 ) { endit = 1; }
        else if ( j >= i || pl_groups_list == "000" ) { checked_pl_groups_all = 1; } // 'j' may include 'undetermined' as a category, which adds 1 to sum, hence the >=

        // See if a single PL is highlighted first in donut chart
        var pls_list = "", pls_footer = "";
        var hilite_pl = $("#jquery_data #current-slice-pl").val();

        if ( hilite_pl != "" ) {
          if ( hilite_pl == "etal" ) {
            pls_list = $("#jquery_data #extra-slices-list").val();
            checked_pls = pls_list.split(",");
          }
          else {
            checked_pls.push(hilite_pl);
          }
        }
        else {
          pls_list = $("#jquery_data #pls-nums-f").val();
          pls_footer = $("#jquery_data #pls-desc-f").val();
          checked_pls = pls_list.split(",");
        }

        var nmin = normalize_date_min(minperiod);
        var nmax = normalize_date_max(maxperiod);

        // Update Top Menu Dates button 'selected' text
        if ( minperiod == maxperiod ) { top_menu_button_date = minperiod; } else { top_menu_button_date = minperiod+ " to " +maxperiod; }
        $("#date_interval span", window.parent.document).text(top_menu_button_date);

        $("#jquery_di_data #di_min", window.parent.document).remove();  // latest min and max settings - any time interval format, as selected by end user
        $("#jquery_di_data #di_max", window.parent.document).remove();
        $("#jquery_di_data", window.parent.document).append("<input type=\"hidden\" id=\"di_min\" name=\"di_min\" value=\""+minperiod+"\">");
        $("#jquery_di_data", window.parent.document).append("<input type=\"hidden\" id=\"di_max\" name=\"di_max\" value=\""+maxperiod+"\">");

        // if Dates (rather than Dates & Intervals) top menu selector is on, set this
        $("#minperiod option:selected", window.parent.document).removeAttr("selected");
        $("#minperiod", window.parent.document).val(minperiod);
        $("#maxperiod option:selected", window.parent.document).removeAttr("selected");
        $("#maxperiod", window.parent.document).val(maxperiod);
        //top_sel_dates
        var top_min = minperiod.slice(0,4)+"/"+minperiod.slice(4);  //201808
        var top_max = maxperiod.slice(0,4)+"/"+maxperiod.slice(4);
        $("#top_sel_dates", window.parent.document).html("Dates<br/><span>"+top_min+" -> "+top_max+"</span>");
        $("#jquery_di_data #di_min_norm", window.parent.document).remove();  // for resetting slider controls -- in yyyy-mm-dd format
        $("#jquery_di_data #di_max_norm", window.parent.document).remove();
        $("#jquery_di_data", window.parent.document).append("<input type=\"hidden\" id=\"di_min_norm\" name=\"di_min_norm\" value=\""+nmin+"\">");
        $("#jquery_di_data", window.parent.document).append("<input type=\"hidden\" id=\"di_max_norm\" name=\"di_max_norm\" value=\""+nmax+"\">");

        // Update Row Zero
        $('#summation').html(currentSummation);

        // UPDATE TOP TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
        buildTopTable(source,checked_pls);
        ttHighlight(cc);

        // Update Meters
        $('.PRR-meter').html(updateMeter1(cc));
        $('.PPRR-meter').html(updateMeter2(cc));

        // UPDATE TREND CHART   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
        // -- Trend chart is only redrawn here in order to add a vertical line at the selected month
        buildTrendChart(source,checked_pl_groups_all,notInit,cc,per,period_idx);


        // UPDATE DONUT CHART   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
        buildDonutChart(source,cc,checked_pls);

        var i = donutChart.config.data.datasets[0].backgroundColor.length;
        i--;
        // save new slice color
        var save_slice_col = donutChart.config.data.datasets[0].backgroundColor[i];
        $("#jquery_data #prior-slice-col").remove();
        $("#jquery_data .h-slice").append('<input type="hidden" id="prior-slice-col" name="prior-slice-col" value="'+save_slice_col+'">');

        // UPDATE BOTTOM TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
        var bt = $( "#bt" ).DataTable();
        $("#bt_re").empty();
        bt.destroy();

        $('#bt_re').addClass("blank");
        updateBottomTable(pl_groups_list, checked_pls, pls_list, "", "trend_chart");

        jQuery("#bt_re").ready(checkContainer);
        jQuery("#bt_re").ready(checkContainer1);
        bottomChartCSV();
        top_table_scroll_to();
      }
    });
  }  // END - ONCLICK OF TREND CHART



  // ONCLICK OF PRODUCT GROUP CHECKLIST  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @
  $("#donut-pgsel .pgsel-checkbox input").click(function() {

    var source = "pg";
    once_is_enough = 0;

    // reset donut chart to remove individual slice selected
    $("#viewer_form #donut_plg", window.parent.document).remove();
    $("#viewer_form #donut_pl", window.parent.document).remove();

    $("#donut-right #all-donut").css("visibility","hidden");
    $("#donut-right #donut-note").css("visibility","hidden");

    var setbox = $(this).closest(".pgsel-checkbox").find("input[type='checkbox']").val();
    var c = this.checked ? '1' : '0';

    // SELECT ALL / NONE
    if ( setbox == "all" ) {
      // apparently after initial DOM completes, checkboxes require setting both 'attr' and 'prop', one to alter DOM and one to display in HTML page code
      $(".pgsel-checkbox input").attr("checked","checked");
      $(".pgsel-checkbox input").prop("checked",true);
      $(".pgsel_check-none").removeAttr("checked");
    }
    else if ( setbox == "none" ) {
      $(".pgsel_check-none").attr("checked","checked");
      setTimeout(function(){ $(".pgsel-checkbox input").removeAttr("checked"); }, 50);
    }
    setTimeout(function(){ // with pretty delay, so ALL/NONE checkbox being checked shows its temporary checked state
      $(".pgsel_check-all").removeAttr("checked");
      $(".pgsel_check-none").removeAttr("checked");
    }, 50);

    product_group_trigger(source,setbox,c);
  });

  // this function is used by Product Group checklist, but ALSO by Donut Chart "Return to All Products" button to reset iframe.
  function product_group_trigger(source,setbox,c) {  // set up this way, as a function, rather than using 'trigger' jquery function, as it otherwise causes multiple submits.

    var minperiod = $('#jquery_di_data #di_min', window.parent.document).val();
    var maxperiod = $('#jquery_di_data #di_max', window.parent.document).val();

    $("#jquery_data #prior-slice-col").remove();
    $("#jquery_data #prior-slice-idx").remove();
    $("#jquery_data #current-slice-pl").remove();
    $("#jquery_data .h-slice").append('<input type="hidden" id="prior-slice-col" name="prior-slice-col" value="">');
    $("#jquery_data .h-slice").append('<input type="hidden" id="prior-slice-idx" name="prior-slice-idx" value="">');
    $("#jquery_data .h-slice").append('<input type="hidden" id="current-slice-pl" name="current-slice-pl" value="">');

    // Need to deliberately set the checkbox as checked/unchecked, after it is selected by user -- this drives other jquery that relies on tracking checked Prod Groups
    if ( typeof setbox !== 'undefined' ) {
      $(".pgsel-checkbox input[value='"+setbox+"']").attr("checked","checked");
      $(".pgsel-checkbox input[value='"+setbox+"']").prop("checked",true);
      if ( c == 1 ) {
        $(".pgsel-checkbox input[value='"+setbox+"']").attr("checked","checked");
        $(".pgsel-checkbox input[value='"+setbox+"']").prop("checked",true);
      }
      else {
        $(".pgsel-checkbox input[value='"+setbox+"']").removeAttr("checked");
        $(".pgsel-checkbox input[value='"+setbox+"']").prop("checked",false);
      }
    }

    var cc = $('#jquery_data #cc').val();
    var period_idx = $('#jquery_data #period-idx').val();

    // where is the selected country
    var country_in = "", country = "", CountryDesc = "", locallevel = "";

    if ( cc != "000" ) {
      $.each(areas_orgs, function(i,v){  //  v = AMT
        $.each(org_obj.org_countries_array, function(j,w){
          if ( org_obj.org_countries_array[j]['org_name'] == v ) {
            if ( org_obj.org_countries_array[j]['country'] == cc ) { country_in = org_obj.org_countries_array[j]['org_name']; }
          }
        });
      });
    }
    else {
      country_in = "WorldWide";
    }

    var prod_group = $(this).closest(".pgsel-checkbox").find("input[type='checkbox']").val();

    // GRAB ALL PLs FOR CHECKED PRODUCT GROUPS
    var pl_groups_list = "";
    checked_pl_groups = []; checked_pls = [];
    // get checked Product Groups
    var endit = 0, checked_pl_groups_all = 0, ct_plg = 0;
    $.each($("input[name='prod_group']:checked"), function(){
      if ( $(this).val() == "none" ) { endit = 1; }
      else if ( $(this).val() == "all" ) { checked_pl_groups_all = 1; }
      if ( endit != 1 && $(this).val() != "all" ) {
        checked_pl_groups.push($(this).val());  pl_groups_list += "'"+$(this).val()+"',";   //getplg += $(this).val()+", ";
        ct_plg++;
      }
    });
    pl_groups_list = pl_groups_list.slice(0,-1);

    // get PLs
    var pls_list = "", pls_footer = "", j0 = "", fail0 = 0;
    $.each(checked_pl_groups, function(i, v){
      try{ j0 = inst_group_data[v]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (j0)
        $.each(inst_group_data[v], function(j, k){
          if ($.inArray(j, checked_pls) == -1) { checked_pls.push(j); pls_list += "'"+j+"',"; pls_footer += k+", "; }
        });
      }
      fail0 = 0; j0 = "";
    });
    pls_list = pls_list.slice(0,-1);
    pls_footer = pls_footer.slice(0,-2);

    // Save Product Group and PL Donut Chart selections
    // CREATING these strings manually, as the resulting URL is far more efficient in terms of size, and will parse the strings manually
    var donut_plg_url = "", donut_pl_url = "";

    if ( checked_pl_groups_all == 1 ) { donut_plg_url = "000"; }
    else { $.each(checked_pl_groups, function(i, v){ donut_plg_url += v+"_";  }); donut_plg_url = donut_plg_url.slice(0,-1); }

    $.each(checked_pls, function(i, v){ donut_pl_url += v+"_"; });
    donut_pl_url = donut_pl_url.slice(0,-1);
    donut_pl_url = donut_pl_url + "--end";

    // POST HIDDEN FORM VALUES
    // URL Query String (Page Level - works during page refresh)
    $("#viewer_form #donut_plg", window.parent.document).remove();
    $("#viewer_form", window.parent.document).append('<input type="hidden" id="donut_plg" name="donut_plg" value="'+donut_plg_url+'">');
    $("#viewer_form #donut_pl", window.parent.document).remove();
    $("#viewer_form", window.parent.document).append('<input type="hidden" id="donut_pl" name="donut_pl" value="'+donut_pl_url+'">');
    // SQL Query String (Frame Level - for populating other divs properly)
    var donut_plg_url_comma = donut_plg_url.replace(/\_/g, ',');
    var donut_pl_url_comma = donut_pl_url.replace(/\_/g, ',');
    donut_pl_url_comma = donut_pl_url_comma.slice(0,-5);
    $("#jquery_data #pls-nums-f").remove();  $("#jquery_data .h-pl-n").append('<input type="hidden" id="pls-nums-f" name="pls-nums-f" value="'+donut_pl_url_comma+'">');
    $("#jquery_data #plgs-f").remove();  $("#jquery_data .h-plg").append('<input type="hidden" id="plgs-f" name="plgs-f" value="'+donut_plg_url_comma+'">');
    // pls desc
    $("#jquery_data #pls-desc-f").remove();  $("#jquery_data .h-pl-d").append('<input type="hidden" id="pls-desc-f" name="pls-desc-f" value="'+pls_footer+'">');

    // Set top menu INSTRUMENTS selector -- only IF 1 Product Group checkbox is checked OR 'ALL' is checked.  Also set related PLs.
    // deselect all plgroups
    $("#plgroup option", window.parent.document).attr("selected", false);

    // remove all existing options
    $("#pl option", window.parent.document).remove();
    // add options back in
    $("#pl", window.parent.document).append($("<option></option>").attr("value","000").text("Use Instrument Family"));

    if ( ct_plg == 1 ) { // ONE PL Group Family
      var optct = 0;
      $.each(inst_data, function(i, v){
        if(jQuery.inArray(i, checked_pls) !== -1 ) { $("#pl", window.parent.document).append($("<option></option>").attr("value",i).text(v)); optct++; }
      });
      // select plgroup and pls
      var checked_plg = '"'+checked_pl_groups[0]+'"';

      $("#plgroup option[value="+checked_plg+"]", window.parent.document).prop({defaultSelected: true});  // set both
      $("#plgroup option[value="+checked_plg+"]", window.parent.document).prop("selected", "selected");

      $("#pl", window.parent.document).val([pls_list]);
      // if all pls under a plgroup are 'selected', then it selects only the 'Use Instrument Family' choice rather than the individual items.

      if ( pls_list.split(',').length == optct ) {
        $("#pl option[value='000']", window.parent.document).prop({defaultSelected: true});
      }
      else {
        $.each(checked_pls, function(i, v){
          v = '"'+v+'"';
          $("#pl option[value="+v+"]", window.parent.document).prop({defaultSelected: true});
        });
      }
      // Update Top Menu Instruments button 'selected' text
      top_menu_button_desc_instr = checked_pl_groups[0]+ " Family";
      $("#instruments span", window.parent.document).text(top_menu_button_desc_instr);

      // POST HIDDEN FORM VALUES
      $("#jquery_data #plg-p").remove();  $("#jquery_data .h-plg").append('<input type="hidden" id="plg-p" name="plg-p" value="'+donut_plg_url_comma+'">');
      $("#jquery_data #pls-desc-p").remove();  $("#jquery_data .h-pl-d").append('<input type="hidden" id="pls-desc-p" name="pls-desc-p" value="'+pls_footer+'">');
      $("#jquery_data #pls-nums-p").remove();  $("#jquery_data .h-pl-n").append('<input type="hidden" id="pls-nums-p" name="pls-nums-p" value="'+donut_pl_url_comma+'">');
    }
    else {  // >1 or ALL PL Groups
      $.each(inst_data_sorted, function(i, v){  // extract key here, since could not sort associately within jquery, though original json list was alphabetical by val
        var lastbracket = v.lastIndexOf("(") +1;
        var grabbrackets = v.substring(lastbracket);
        i = grabbrackets.slice(0,-1);
        $("#pl", window.parent.document).append($("<option></option>").attr("value",i).text(v));
      });

      // select plgroup and pls
      $("#plgroup option[value='000']", window.parent.document).prop({defaultSelected: true});  // weirdly, needs both set, to both have 'selected' in html and to highlight gray
      $("#plgroup option[value='000']", window.parent.document).prop("selected", "selected");

      if ( checked_pl_groups_all == 1 || setbox == "ALL" ) {
        $("#pl", window.parent.document).val([pls_list]); // not sure this itemized list of pls is needed here, if plgroups is '000'
        $("#pl option[value='000']", window.parent.document).prop({defaultSelected: true});

        // Update Top Menu Instruments button 'selected' text
        top_menu_button_desc_instr = "All Instruments";
        $("#instruments span", window.parent.document).text(top_menu_button_desc_instr);
      }
      else { // cycle through all checked pls (as driven by pg list) and check those Instruments, under Instrument Family "All Instruments", as active.
        $.each(checked_pls, function(i, v){
          v = '"'+v+'"';
          $("#pl option[value="+v+"]", window.parent.document).prop({defaultSelected: true});
        });

        // Update Top Menu Instruments button 'selected' text
        top_menu_button_desc_instr = "Multiple Selected";
        $("#instruments span", window.parent.document).text(top_menu_button_desc_instr);
      }

      // POST HIDDEN FORM VALUES
      var all_plgs = $('#jquery_data .h-all-pl #all-plgs').val();
      var all_pl_num = $('#jquery_data .h-all-pl #all-pl-num').val();
      var all_pl_desc = $('#jquery_data .h-all-pl #all-pl-desc').val();
      $("#jquery_data #plg-p").remove();  $("#jquery_data .h-plg").append('<input type="hidden" id="plg-p" name="plg-p" value="'+all_plgs+'">');
      $("#jquery_data #pls-desc-p").remove();  $("#jquery_data .h-pl-d").append('<input type="hidden" id="pls-desc-p" name="pls-desc-p" value="'+all_pl_desc+'">');
      $("#jquery_data #pls-nums-p").remove();  $("#jquery_data .h-pl-n").append('<input type="hidden" id="pls-nums-p" name="pls-nums-p" value="'+all_pl_num+'">');
    }

    if ( ct_plg > 1 ) {
      // Update Top Menu Instruments button 'selected' text
      top_menu_button_desc_instr = " ";
      if ( ct_plg > 2 ) {
        top_menu_button_desc_instr = "Multiple Selected";
      }
      else {
        $.each(checked_pl_groups, function(i, v){
          top_menu_button_desc_instr += " " +checked_pl_groups[i]+ " Family, ";
        });
        top_menu_button_desc_instr = top_menu_button_desc_instr.slice(0,-2);
      }
    }
    else if ( endit == 1 ) {
    top_menu_button_desc_instr = "none";
    }

    $('#summation').html(currentSummation);

    // UPDATE TOP TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    if ( endit == 0 ) {

      buildTopTable(source,checked_pls);
      ttHighlight(cc);

      // Update Meters
      $('.PRR-meter').html(updateMeter1(cc));
      $('.PPRR-meter').html(updateMeter2(cc));
    }
    else {  // blank out all rows, since Product Group 'none' was selected in donut table checkboxes
      $("#tt tbody").find('tr').find(".td-totl").text(0);
      $("#tt tbody").find('tr').find(".td-hr-c").text(0);
      $("#tt tbody").find('tr').find(".td-ph-c").text(0);
      $("#tt tbody").find('tr').find(".td-ph-p").text(0+" %");
      $("#tt tbody").find('tr').find(".td-ph-p").attr('data-prr',0);
      $("#tt tbody").find('tr').find(".td-ph-p").attr("style", "background-size: 0% 80%; background-position: right center;");
      $("#tt tbody").find('tr').find(".td-pr-c").text(0);
      $("#tt tbody").find('tr').find(".td-pr-p").text(0+" %");
      $("#tt tbody").find('tr').find(".td-pr-p").attr('data-pprr',0);
      $("#tt tbody").find('tr').find(".td-pr-p").attr("style", "background-size: 0% 80%; background-position: right center;");
      $("#tt tbody").find('tr').find(".td-dr-c").text(0);
      $("#tt tbody").find('tr').find(".td-dr-p").text(0+" %");
      $("#tt tbody").find('tr').find(".td-dr-p").attr('data-dis',0);
      $("#tt tbody").find('tr').find(".td-dr-p").attr("style", "background-size: 0% 80%; background-position: right center;");
      $("#tt tbody").find('tr').find(".td-km-c").text(0);
      $("#tt tbody").find('tr').find(".td-km-p").text(0+" %");
      $("#tt tbody").find('tr').find(".td-km-p").attr('data-km_rate',0);
      $("#tt tbody").find('tr').find(".td-km-p").attr("style", "background-size: 0% 80%; background-position: right center;");
      $("#tt tbody").find('tr').find(".td-al-c").text(0);
      $("#tt tbody").find('tr').find(".td-al-p").text(0+" %");
      $("#tt tbody").find('tr').find(".td-al-p").attr('data-al_rate',0);
      $("#tt tbody").find('tr').find(".td-al-p").attr("style", "background-size: 0% 80%; background-position: right center;");

      $("#tt-foot tfoot").find('tr').find(".td-all-totl").text(0);
      $("#tt-foot tfoot").find('tr').find(".td-all-avg-hrs-pf").text(0);
      $("#tt-foot tfoot").find('tr').find(".td-all-ph-c").text(0);
      $("#tt-foot tfoot").find('tr').find(".td-all-ph-p").text(0+" %");
      $("#tt-foot tfoot").find('tr').find(".td-all-ph-p").attr('data-prr',0);
      $("#tt-foot tfoot").find('tr').find(".td-all-dr-c").text(0);
      $("#tt-foot tfoot").find('tr').find(".td-all-dr-p").text(0+" %");
      $("#tt-foot tfoot").find('tr').find(".td-all-dr-p").attr('data-dis',0);
      $("#tt-foot tfoot").find('tr').find(".td-all-pr-c").text(0);
      $("#tt-foot tfoot").find('tr').find(".td-all-pr-p").text(0+" %");
      $("#tt-foot tfoot").find('tr').find(".td-all-pr-p").attr('data-pprr',0);
      $("#tt-foot tfoot").find('tr').find(".td-all-km-c").text(0);
      $("#tt-foot tfoot").find('tr').find(".td-all-km-p").text(0+" %");
      $("#tt-foot tfoot").find('tr').find(".td-all-km-p").attr('data-km_rate',0);
      $("#tt-foot tfoot").find('tr').find(".td-all-al-c").text(0);
      $("#tt-foot tfoot").find('tr').find(".td-all-al-p").text(0+" %");
      $("#tt-foot tfoot").find('tr').find(".td-all-al-p").attr('data-al_rate',0);

      // Zero out meters
      $(".PRR-meter").attr("src", "overall_meter.php?value=0&title=Phone Resolution Rate&color=0x82c341");
      $(".PPRR-meter").attr("src", "overall_meter.php?value=0&title=Proactive Phone Resolution Rate&color=0xf2c80f");

      // checksum - post sum of slices (should match sum of tickets)
      $("#donut-checksum").html("<b>Ticket Sum:</b> 0");
    }

    // UPDATE TREND CHART   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    buildTrendChart(source,checked_pl_groups_all,notInit,cc,per,period_idx);


    // UPDATE DONUT CHART   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    buildDonutChart(source,cc,checked_pls);

    var i = donutChart.config.data.datasets[0].backgroundColor.length;
    i--;
    // save new slice color
    var save_slice_col = donutChart.config.data.datasets[0].backgroundColor[i];
    $("#jquery_data #prior-slice-col").remove();
    $("#jquery_data .h-slice").append('<input type="hidden" id="prior-slice-col" name="prior-slice-col" value="'+save_slice_col+'">');

    // UPDATE BOTTOM TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    if ( endit == 0 ) {
      // Generate URL string
      var bt = $( "#bt" ).DataTable();
      $("#bt_re").empty();
      bt.destroy();

      $('#bt_re').addClass("blank");
      updateBottomTable(pl_groups_list, checked_pls, pls_list, "", "doughnut_chart");

      jQuery("#bt_re").ready(checkContainer);
      jQuery("#bt_re").ready(checkContainer1);
      bottomChartCSV();
    }
    else {
      $('#bt_re').addClass("blank");
      //$("#content-wrap").removeClass("content-freeze");
    }

    top_table_scroll_to();

    // FOOTER
    var sg_footer = "", bs_footer = "", w = "";
    buildFooter(pls_footer,sg_footer,bs_footer);

  }  // END -- ONCLICK OF PRODUCT GROUP CHECKLIST


  // ONCLICK OF DONUT CHART "RETURN TO ALL PRODUCTS" BUTTON  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @
  $("#all-donut").click(function() {

    var source = "donut";
    //$("#content-wrap").addClass("content-freeze");
    once_is_enough = 0;

    $("#viewer_form #donut_plg", window.parent.document).remove();
    $("#viewer_form #donut_pl", window.parent.document).remove();

    $("#donut-right #all-donut").css("visibility","hidden");
    $("#donut-right #donut-note").css("visibility","hidden");

    var setbox = "ALL";
    var c = 0;

    product_group_trigger(source,setbox,c);
  });


  // ONCLICK OF DONUT   @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @
  function donutClick() {

    var source = "donut";
    once_is_enough = 0;

    $("#donutChart").click(function(e) {

      $("#donut-right #all-donut").css("visibility","visible");
      $("#donut-right #donut-note").css("visibility","visible");

      var slicea = donutChart.getElementsAtEvent(e);

      if (slicea[0]) {
        var chartData = slicea[0]['_chart'].config.data;
        var idx = slicea[0]['_index'];

        var label = chartData.labels[idx];
        var value = chartData.datasets[0].data[idx];

        // HIGHLIGHT ACTIVE SLICE
        // get existing slice color, if any
        var prior_slice_col = $('#jquery_data #prior-slice-col').val();
        var prior_slice_idx = $('#jquery_data #prior-slice-idx').val();

        chartData.datasets[0].backgroundColor[prior_slice_idx] = prior_slice_col;
        chartData.datasets[0].borderColor[prior_slice_idx] = prior_slice_col;
        donutChart.update();

        // failsafe - walk through all colors to make sure there is no yellow (hilite) -- happens when person clicks too quickly between slices, and 'doc ready' did not remedy it
        $.each(chartData.datasets[0].backgroundColor, function(i,v){
          if ( v == "#fdf581" ) {
            chartData.datasets[0].backgroundColor[i] = "#69c"; // innocuous patch-in color, quicker than devising a random color
            chartData.datasets[0].borderColor[i] = "#69c";
          }
        });

        // save new slice color and index
        var save_slice_col = chartData.datasets[0].backgroundColor[idx];
        var save_slice_idx = idx;
        $("#jquery_data #prior-slice-col").remove();
        $("#jquery_data #prior-slice-idx").remove();
        $("#jquery_data .h-slice").append('<input type="hidden" id="prior-slice-col" name="prior-slice-col" value="'+save_slice_col+'">');
        $("#jquery_data .h-slice").append('<input type="hidden" id="prior-slice-idx" name="prior-slice-idx" value="'+save_slice_idx+'">');

        // highlight selected slice
        chartData.datasets[0].backgroundColor[idx] = '#fdf581';  // 'rgba(253,245,129,1)';
        chartData.datasets[0].borderColor[idx] = '#003377';
        donutChart.update();

        var leftbracket = label.lastIndexOf("(") +1;
        if (leftbracket) {
          var rightbracket = label.lastIndexOf(")");
          var grabbrackets = label.substring(leftbracket,rightbracket);
          k = grabbrackets; //.slice(0,-1);
        }
        else {
          k = "etal";  // and other
        }

        $("#jquery_data #current-slice-pl").remove();
        $("#jquery_data .h-slice").append('<input type="hidden" id="current-slice-pl" name="current-slice-pl" value="'+k+'">');

        var prod_group = "", j0 = "", fail0 = 0;
        if ( k == "etal" ) {
          prod_group = "ALL"; // not "000", which implies all Product Groups are selected, this "ALL" designation means "All Instruments" under Instrument Family is selected.
        }
        else {
          $.each(inst_group_data, function(i,v){
            if ( i != "Flagship" && prod_group == "" ) {
              try{ j0 = inst_group_data[i]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (j0) {
              $.each(inst_group_data[i], function(j,w){
                if ( j == k ) { prod_group = i; }
              });
              } }
              fail0 = 0; j0 = "";
            }
          });
        }


        var cc = $('#jquery_data #cc').val();
        var period_idx = $('#jquery_data #period-idx').val();

        // where is the selected country
        var country_in = "", country = "", CountryDesc = "", locallevel = "";

        if ( cc !== "000" ) {
          $.each(areas_orgs, function(i,v){  //  v = AMT
            $.each(org_obj.org_countries_array, function(j,w){
              if ( org_obj.org_countries_array[j]['org_name'] == v ) {
                if ( org_obj.org_countries_array[j]['country'] == cc ) { country_in = org_obj.org_countries_array[j]['org_name'];  }
              }
            });
          });
        }
        else {
          country_in = "WorldWide";
        }

        // GRAB ALL PLs FOR CHECKED PRODUCT GROUPS
        var pl_keys = "", pl_vals = "", prod_group_in = "", pls_list = "", pls_footer = "";
        ctry_pl = []; checked_pl_groups = []; checked_pls = [];

        var pl_groups_list = prod_group;

        if ( k == "etal" ) {
          prod_group_in = "000";
          checked_pl_groups.push(prod_group_in);
          var extra_slices_list = $('#jquery_data #extra-slices-list').val();
          checked_pls = extra_slices_list.split(',');
          pls_list = extra_slices_list;
          var firstcolon = label.lastIndexOf(":");
          $.each(checked_pls, function(i, v){
            pls_footer += inst_data[v]+ ", ";
          });
        }
        else {
          prod_group_in = prod_group;
          checked_pl_groups.push(prod_group_in);
          checked_pls.push(k);
          pls_list = k;
          label = inst_data[k];
          pls_footer = label;
        }

        // POST HIDDEN FORM VALUES
        $("#jquery_data #plg-p").remove();  $("#jquery_data .h-plg").append('<input type="hidden" id="plg-p" name="plg-p" value="'+pl_groups_list+'">');
        $("#jquery_data #pls-desc-p").remove();  $("#jquery_data .h-pl-d").append('<input type="hidden" id="pls-desc-p" name="pls-desc-p" value="'+pls_footer+'">');
        $("#jquery_data #pls-desc-f").remove();  $("#jquery_data .h-pl-d").append('<input type="hidden" id="pls-desc-f" name="pls-desc-f" value="'+pls_footer+'">');
        $("#jquery_data #pls-nums-p").remove();  $("#jquery_data .h-pl-n").append('<input type="hidden" id="pls-nums-p" name="pls-nums-p" value="'+pls_list+'">');
        $("#jquery_data #pls-nums-f").remove();  $("#jquery_data .h-pl-n").append('<input type="hidden" id="pls-nums-f" name="pls-nums-f" value="'+pls_list+'">');

        // Save Product Group and PL Donut Chart selections, CREATING these strings manually
        var donut_plg_url = prod_group;
        var donut_pl_url = pls_list+ "--end";

        if ( donut_plg_url =="ALL" ) { donut_plg_url = "All Instruments"; }

        // Remove any prior instances
        $("#viewer_form #donut_plg", window.parent.document).remove();
        $("#viewer_form #donut_pl", window.parent.document).remove();
        // add back in
        $("#viewer_form", window.parent.document).append('<input type="hidden" id="donut_plg" name="donut_plg" value="'+donut_plg_url+'">');
        $("#viewer_form", window.parent.document).append('<input type="hidden" id="donut_pl" name="donut_pl" value="'+donut_pl_url+'">');

        // Set top menu INSTRUMENTS selector -- Product Group checkbox and related PL.
        // deselect all plgroups
        $("#plgroup option", window.parent.document).attr("selected", false);

        // remove all existing options
        $("#pl option", window.parent.document).remove();
        // add options back in
        $("#pl", window.parent.document).append($("<option></option>").attr("value","000").text("Use Instrument Family"));

        if ( prod_group == "ALL" ) {
          var all_pls = $('#jquery_data #all-pl-desc').val();
          var all_pls = all_pls.split(',');
          var all_pls_by_value = all_pls.slice(0);  // use slice() to copy the array and not just make a reference
          all_pls_by_value.sort(function(a,b) { return a.v - b.v; });
          all_pls = [];
          $.each(all_pls_by_value, function(i, v){  // populate top Instruments box, for total correllation of Pls with PL nums, extract PL num from desc.
            var lastbracket = v.lastIndexOf("(") +1;
            var i = v.substring(lastbracket,v.length -1);
            $("#pl", window.parent.document).append($("<option></option>").attr("value",i).text(v));
          });
        }
        else {
          $.each(inst_group_data[prod_group], function(i, v){
            $("#pl", window.parent.document).append($("<option></option>").attr("value",i).text(v));
          });
        }

        // select plgroup and pls
        var checked_plg = '"'+checked_pl_groups[0]+'"';
        $("#plgroup option[value="+checked_plg+"]", window.parent.document).prop({defaultSelected: true});  // set both
        $("#plgroup option[value="+checked_plg+"]", window.parent.document).prop("selected", "selected");
        $("#pl", window.parent.document).val([pls_list]);

        $.each(checked_pls, function(i, v){
          v = '"'+v+'"';
          $("#pl option[value="+v+"]", window.parent.document).prop({defaultSelected: true});
        });

        // Update Top Menu Instruments button 'selected' text
        if ( k == "etal" ) { top_menu_button_desc_instr = "Donut Chart - "+label.slice(2,firstcolon); }
        else { top_menu_button_desc_instr = label; }

        $("#instruments span", window.parent.document).text(top_menu_button_desc_instr);

        // Update Row Zero
        $('#summation').html(currentSummation);

        var single_period = $("#jquery_data #period-val").val();

        if ( single_period != "" ) {
          minperiod = single_period;
          maxperiod = single_period;
        }
        else {
          minperiod = "<?php echo $minperiod; ?>";
          maxperiod = "<?php echo $maxperiod; ?>";
        }

        // UPDATE TOP TABLE  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
        buildTopTable(source,checked_pls);
        ttHighlight(cc);

        // Update Meters
        $('.PRR-meter').html(updateMeter1(cc));
        $('.PPRR-meter').html(updateMeter2(cc));

        // UPDATE TREND CHART   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
        var checked_pl_groups_all = "";
        buildTrendChart(source,checked_pl_groups_all,notInit,cc,per,period_idx);


        // UPDATE BOTTOM TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
        var bt = $( "#bt" ).DataTable();
        $("#bt_re").empty();
        bt.destroy();

        updateBottomTable(pl_groups_list, checked_pls, pls_list, "", "doughnut_chart");

        jQuery("#bt_re").ready(checkContainer);
        jQuery("#bt_re").ready(checkContainer1);
        bottomChartCSV();
        top_table_scroll_to();

        // FOOTER
        var sg_footer = "", bs_footer = "", w = "";
        buildFooter(pls_footer,sg_footer,bs_footer);
      }
    });
  }  // END - ONCLICK OF DONUT


  // ONCLICK OF PRR STACKED CHART AREA/REGION/LOCATION BUTTONS  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @
  $("#stacked-selector").click(function(e) {
    var clicked_stack = e.target.id;
    stacked_chart_trigger(clicked_stack);
  });


  // PRR STACKED CHART  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @
  function stacked_chart_trigger(clicked_stack) {

    var fail0 = 0; var i0 = "";  var err_org = ""; var err_org_URL_encoded = "";
    var err_phone_fix = {}; var err_total_calls = {}; var err_cc = {};
    var errURL = ""; var orgURL = "";

    $.each(toperr, function(i, v){ err_phone_fix[v.i] = {}; err_total_calls[v.i] = {}; errURL += v.i+ "~~"; });
    errURL = errURL.slice(0,-2);

    if ( sub_type == "ctry" ) {
      var org_scope = "";

      if ( clicked_stack == "stacked-selector1" ) { org_scope = "area"; }
      else if ( clicked_stack == "stacked-selector2" ) { org_scope = "region"; }
      else if ( clicked_stack == "stacked-selector3" ) { org_scope = "location"; }
      else { org_scope = ""; }
      // area, location, region

      if ( org_scope == "area" ) {
        $.each(areas_orgs, function(i,v){   //  v = 'AMT'
          err_org = v;
          err_org_URL_encoded = err_org.replace(/\ /g,"_");
          orgURL += err_org_URL_encoded+ ",";
          $.each(org_obj.org_countries_array, function(j,w){
            if ( org_obj.org_countries_array[j]['org_name'] == err_org ) {
              $.each(toperr, function(i, v){
                fail0 = 0; i0 = "";
                try{ i0 = data.entity_country.phone_fix[v.i][org_obj.org_countries_array[j]['country']]; }catch(e){ if(e){ fail0 = 1; }}
                if ( fail0 == 0 ) {
                  if (typeof i0 !== "undefined" && i0 != "") {
                    if(err_phone_fix[v.i][err_org]) { err_phone_fix[v.i][err_org] += i0; }  // var[error][region]
                    else { err_phone_fix[v.i][err_org] = i0; }
                  }
                }
                fail0 = 0; i0 = "";
                try{ i0 = data.entity_country.total_calls[v.i][org_obj.org_countries_array[j]['country']]; }catch(e){ if(e){ fail0 = 1; }}
                if ( fail0 == 0 ) {
                  if (typeof i0 !== "undefined" && i0 != "") {
                    if(err_total_calls[v.i][err_org]) { err_total_calls[v.i][err_org] += i0;  }
                    else { err_total_calls[v.i][err_org] = i0; }
                  }
                }
              });
            }
          });
        });
        orgURL = orgURL.slice(0,-1);

        var pct = 0; var sumtotalcalls = 0; var pctURL = ""; var sumURL = ""; var err_err = ""; var err_org = "";
        $.each(areas_orgs, function(i,v){
          err_org = v;
          $.each(toperr, function(i, v){
            err_err = v.i;
            fail0 = 0; i0 = 0;
            try{ i0 = err_total_calls[err_err][err_org]; }catch(e){ if(e){ fail0 = 1; }}
            if ( fail0 == 0 ) {
              if (typeof i0 !== "undefined" && i0 != 0) {
                if ( typeof err_phone_fix[err_err][err_org] === "undefined" ) { err_phone_fix[err_err][err_org] = 0; }
                pct = (Math.round(err_phone_fix[err_err][err_org] / i0 *100 *1)/1);
                sumtotalcalls = i0;
              }
              else { pct = 0; sumtotalcalls = 0; }
            }
            else { pct = 0; sumtotalcalls = 0; }
            pctURL += pct+ ",";
            sumURL += sumtotalcalls+ ",";
          });
          pctURL = pctURL.slice(0,-1);
          sumURL = sumURL.slice(0,-1);
          pctURL += ";";
          sumURL += ";";
        });
      }
      else if ( org_scope == "location" ) {    // location (country)

        if ( data == 0 || typeof data === 'undefined' ) {
          data = JSON.parse( $("#jquery_data #sql_data").text() );
        }

        $.each(org_obj.countries_array, function(j,w){
          $.each(toperr, function(i, v){
            fail0 = 0; i0 = "";  err_org = j;
            try{ i0 = data.entity_country.phone_fix[v.i][j]; }catch(e){ if(e){ fail0 = 1; }}

            if ( fail0 == 0 ) {
              if (typeof i0 !== "undefined" && i0 != "") {
                if(err_phone_fix[v.i][err_org]) { err_phone_fix[v.i][err_org] += i0; }  // var[error][region]
                else { err_phone_fix[v.i][err_org] = i0; }
              }
            }
            fail0 = 0; i0 = "";
            try{ i0 = data.entity_country.total_calls[v.i][j]; }catch(e){ if(e){ fail0 = 1; }}
            if ( fail0 == 0 ) {
              if (typeof i0 !== "undefined" && i0 != "") {
                if(err_total_calls[v.i][err_org]) { err_total_calls[v.i][err_org] += i0; }
                else { err_total_calls[v.i][err_org] = i0; }

                if(err_cc[err_org]) { err_cc[err_org] += i0;  }
                else { err_cc[err_org] = i0; }
              }
            }
          });
        });

        err_cc_temp = [];

        $.each(err_cc, function(k, v) { err_cc_temp.push({i:k, v: v}); });
        err_cc_temp.sort(function(a,b) { return a.v - b.v; });
        err_cc_temp.reverse();

        $.each(err_cc_temp, function(i, v){
          orgURL += org_obj.countries_array[v.i] +",";
        });
        orgURL = orgURL.slice(0,-1);

        var pct = 0; var sumtotalcalls = 0; var pctURL = ""; var sumURL = ""; var err_err = ""; var err_org = "";
        $.each(err_cc_temp, function(i,v){
          err_org = v.i;
          $.each(toperr, function(i, v){
            err_err = v.i;
            fail0 = 0; i0 = 0;
            try{ i0 = err_total_calls[err_err][err_org]; }catch(e){ if(e){ fail0 = 1; }}
            if ( fail0 == 0 ) {
              if (typeof i0 !== "undefined" && i0 != 0) {
                if ( typeof err_phone_fix[err_err][err_org] === "undefined" ) { err_phone_fix[err_err][err_org] = 0; }
                pct = (Math.round(err_phone_fix[err_err][err_org] / i0 *100 *1)/1);
                sumtotalcalls = i0;
              }
              else { pct = 0; sumtotalcalls = 0; }
            }
            else { pct = 0; sumtotalcalls = 0; }
            pctURL += pct+ ",";
            sumURL += sumtotalcalls+ ",";
          });
          pctURL = pctURL.slice(0,-1);
          sumURL = sumURL.slice(0,-1);
          pctURL += ";";
          sumURL += ";";
        });
      }
      else {  // currently defaults to by REGION

        if ( data == 0 || typeof data === 'undefined' ) {
          data = JSON.parse( $("#jquery_data #sql_data").text() );
        }

        $.each(regions_orgs, function(i,v){  //  v = 'AE Baltics'
          err_org = v;

          orgURL += err_org+ ",";
          $.each(org_obj.org_countries_array, function(j,w){
            if ( org_obj.org_countries_array[j]['org_name'] == err_org ) {
              $.each(toperr, function(i, v){
                fail0 = 0; i0 = "";
                try{ i0 = data.entity_country.phone_fix[v.i][org_obj.org_countries_array[j]['country']]; }catch(e){ if(e){ fail0 = 1; }}
                if ( fail0 == 0 ) {
                  if (typeof i0 !== "undefined" && i0 != "") {
                    if(err_phone_fix[v.i][err_org]) { err_phone_fix[v.i][err_org] += i0; }  // var[error][region]
                    else { err_phone_fix[v.i][err_org] = i0; }
                  }
                }
                fail0 = 0; i0 = "";
                try{ i0 = data.entity_country.total_calls[v.i][org_obj.org_countries_array[j]['country']]; }catch(e){ if(e){ fail0 = 1; }}
                if ( fail0 == 0 ) {
                  if (typeof i0 !== "undefined" && i0 != "") {
                  if(err_total_calls[v.i][err_org]) { err_total_calls[v.i][err_org] += i0;  }
                    else { err_total_calls[v.i][err_org] = i0; }
                  }
                }
              });
            }
          });
        });
        orgURL = orgURL.slice(0,-1);

        var pct = 0; var sumtotalcalls = 0; var pctURL = ""; var sumURL = ""; var err_err = ""; var err_org = "";
        $.each(regions_orgs, function(i,v){
          err_org = v;
          $.each(toperr, function(i, v){
            err_err = v.i;
            fail0 = 0; i0 = 0;
            try{ i0 = err_total_calls[err_err][err_org]; }catch(e){ if(e){ fail0 = 1; }}
            if ( fail0 == 0 ) {
              if (typeof i0 !== "undefined" && i0 != 0) {
                if ( typeof err_phone_fix[err_err][err_org] === "undefined" ) { err_phone_fix[err_err][err_org] = 0; }
                pct = (Math.round(err_phone_fix[err_err][err_org] / i0 *100 *1)/1);
                sumtotalcalls = i0;
              }
              else { pct = 0; sumtotalcalls = 0; }
            }
            else { pct = 0; sumtotalcalls = 0; }
            pctURL += pct+ ",";
            sumURL += sumtotalcalls+ ",";
          });
          pctURL = pctURL.slice(0,-1);
          sumURL = sumURL.slice(0,-1);
          pctURL += ";";
          sumURL += ";";
        });
      }
    }
    else if ( sub_type == "ind" ) {
      $.each(stacked_display_order, function(j,w){
        $.each(toperr, function(i, v){
          fail0 = 0; i0 = "";  err_org = w;
          try{ i0 = data.entity_country.phone_fix[v.i][w]; }catch(e){ if(e){ fail0 = 1; }}
          if ( fail0 == 0 ) {
            if (typeof i0 !== "undefined" && i0 != "") {
              if(err_phone_fix[v.i][err_org]) { err_phone_fix[v.i][err_org] += i0; }  // var[error][region]
              else { err_phone_fix[v.i][err_org] = i0; }
            }
          }
          fail0 = 0; i0 = "";
          try{ i0 = data.entity_country.total_calls[v.i][w]; }catch(e){ if(e){ fail0 = 1; }}
          if ( fail0 == 0 ) {
            if (typeof i0 !== "undefined" && i0 != "") {
              if(err_total_calls[v.i][err_org]) { err_total_calls[v.i][err_org] += i0; }
              else { err_total_calls[v.i][err_org] = i0; }

              if(err_cc[err_org]) { err_cc[err_org] += i0;  }
              else { err_cc[err_org] = i0; }
            }
          }
        });
      });

      $.each(err_cc, function(i, v){
        orgURL += i +",";
      });
      orgURL = orgURL.slice(0,-1);


      var pct = 0; var sumtotalcalls = 0; var pctURL = ""; var sumURL = ""; var err_err = ""; var err_org = "";
      $.each(err_cc, function(i,v){
        err_org = i;
        $.each(toperr, function(i, v){
          err_err = v.i;
          fail0 = 0; i0 = 0;
          try{ i0 = err_total_calls[err_err][err_org]; }catch(e){ if(e){ fail0 = 1; }}
          if ( fail0 == 0 ) {
            if (typeof i0 !== "undefined" && i0 != 0) {
              if ( typeof err_phone_fix[err_err][err_org] === "undefined" ) { err_phone_fix[err_err][err_org] = 0; }
              pct = (Math.round(err_phone_fix[err_err][err_org] / i0 *100 *1)/1);
              sumtotalcalls = i0;
            }
            else { pct = 0; sumtotalcalls = 0; }
          }
          else { pct = 0; sumtotalcalls = 0; }
          pctURL += pct+ ",";
          sumURL += sumtotalcalls+ ",";
        });
        pctURL = pctURL.slice(0,-1);
        sumURL = sumURL.slice(0,-1);
        pctURL += ";";
        sumURL += ";";
      });
    }
    else { // cntr - center
      $.each(stacked_display_order, function(j,w){
        $.each(toperr, function(i, v){
          fail0 = 0; i0 = "";  err_org = w;
          try{ i0 = data.entity_country.phone_fix[v.i][w]; }catch(e){ if(e){ fail0 = 1; }}
          if ( fail0 == 0 ) {
            if (typeof i0 !== "undefined" && i0 != "") {
              if(err_phone_fix[v.i][err_org]) { err_phone_fix[v.i][err_org] += i0; }  // var[error][region]
              else { err_phone_fix[v.i][err_org] = i0; }
            }
          }
          fail0 = 0; i0 = "";
          try{ i0 = data.entity_country.total_calls[v.i][w]; }catch(e){ if(e){ fail0 = 1; }}
          if ( fail0 == 0 ) {
            if (typeof i0 !== "undefined" && i0 != "") {
              if(err_total_calls[v.i][err_org]) { err_total_calls[v.i][err_org] += i0; }
              else { err_total_calls[v.i][err_org] = i0; }

              if(err_cc[err_org]) { err_cc[err_org] += i0;  }
              else { err_cc[err_org] = i0; }
            }
          }
        });
      });

      $.each(err_cc, function(i, v){
        var center_name = data.center_mapping[i];
        orgURL += center_name +",";
      });
      orgURL = orgURL.slice(0,-1);


      var pct = 0; var sumtotalcalls = 0; var pctURL = ""; var sumURL = ""; var err_err = ""; var err_org = "";
      $.each(err_cc, function(i,v){
        err_org = i;
        $.each(toperr, function(i, v){
          err_err = v.i;
          fail0 = 0; i0 = 0;
          try{ i0 = err_total_calls[err_err][err_org]; }catch(e){ if(e){ fail0 = 1; }}
          if ( fail0 == 0 ) {
            if (typeof i0 !== "undefined" && i0 != 0) {
              if ( typeof err_phone_fix[err_err][err_org] === "undefined" ) { err_phone_fix[err_err][err_org] = 0; }
              pct = (Math.round(err_phone_fix[err_err][err_org] / i0 *100 *1)/1);
              sumtotalcalls = i0;
            }
            else { pct = 0; sumtotalcalls = 0; }
          }
          else { pct = 0; sumtotalcalls = 0; }
          pctURL += pct+ ",";
          sumURL += sumtotalcalls+ ",";
        });
        pctURL = pctURL.slice(0,-1);
        sumURL = sumURL.slice(0,-1);
        pctURL += ";";
        sumURL += ";";
      });
    }

    $("#jquery_data #stacked-scope").remove();
    $("#jquery_data #stacked-sub_type").remove();
    $("#jquery_data #stacked-errs").remove();
    $("#jquery_data #stacked-orgs").remove();
    $("#jquery_data #stacked-sums").remove();
    $("#jquery_data #stacked-pcts").remove();
    $("#jquery_data .stacked-chart").append('<input type="hidden" id="stacked-scope" name="stacked-scope" value="'+org_scope+'">');
    $("#jquery_data .stacked-chart").append('<input type="hidden" id="stacked-sub_type" name="stacked-sub_type" value="'+sub_type+'">');
    $("#jquery_data .stacked-chart").append('<input type="hidden" id="stacked-errs" name="stacked-errs" value="'+errURL+'">');
    $("#jquery_data .stacked-chart").append('<input type="hidden" id="stacked-orgs" name="stacked-orgs" value="'+orgURL+'">');
    $("#jquery_data .stacked-chart").append('<input type="hidden" id="stacked-sums" name="stacked-sums" value="'+sumURL+'">');
    $("#jquery_data .stacked-chart").append('<input type="hidden" id="stacked-pcts" name="stacked-pcts" value="'+pctURL+'">');

    var resetStackedCanvas = function(){  // apparently, this extra code is needed to completely wipe and redraw chart, including click map, etc.
      $('#stackedChart').remove();
      $(".chartjs-size-monitor").remove();
      $('#stacked_prr').append('<canvas id="stackedChart" height="300"></canvas>');
      canvas = document.querySelector('#stackedChart');
    };
    resetStackedCanvas();

    // a smaller chart, no need for scroll bars, is adequate in some cases -- so reset the canvas width
    //  wide: subtype ind, orgscope location, orgscope region
    //  narrow: subtype cntr, orgscope area,
    if ( ( sub_type == "ctry" && org_scope == "area" ) || sub_type == "cntr" ) {
      $("#stacked_prr").css("width","1100px");
      $("#stackedChart").css("width","1100px");
    }
    else {
      $("#stacked_prr").css("width","1650px");
      $("#stackedChart").css("width","1650px");
    }

    $(document).ready(function() {
      $('#stackedChart').load('stacked-chart_chart-js.php');
    });

    $("#stacked_scroll").css("width","1100px !important"); // make these size adjustments POST creation of the chart, to get the scrolling to stick.
    $("#stackedChart").css("width","1600px !important");
    $("#stackedChart").css("height","300px !important");

    // some extra settings - compensates for fact top table does not show labor hours under prr_ex report
    var timer_cutout3 = 0;
    jQuery("#stackedChart").ready(checkContainer3);
    function checkContainer3 () {
      if($('#stackedChart').is(':visible')){
        if ( sub_type == "cntr" || sub_type == "ind" ) {
        $("#stackedChart").css("top","15px");
        }
      }
      else { if ( timer_cutout3 < 300 ) { setTimeout(checkContainer3, 1000);
      timer_cutout3++; } }
    }

    // TOOLTIP - STACKED CHART, PRR By Region Chart
    $("#stackedChart").on("mousemove", function(e) {
      var activePoints = myChart.getElementsAtEventForMode(e, 'point', myChart.options);
      var firstPoint = activePoints[0];
      if (firstPoint) {
        var exp = myChart.data.labels[firstPoint._index];
        var pct = myChart.data.datasets[firstPoint._datasetIndex].data[firstPoint._index];
        var region = myChart.data.datasets[firstPoint._datasetIndex].label;
        var value = myChart.data.datasets[firstPoint._datasetIndex].code[firstPoint._index];

        var Offset = $("#stacked_prr").offset();
        var relX = e.pageX - Offset.left +20;
        var relY = e.pageY - Offset.top -390;

        setTimeout(function() {
          $("#stacked-tooltip").remove();
          $("#stacked_prr").append('<div id="stacked-tooltip" style="left: ' +relX+ 'px; top: ' +relY+ 'px;"><div class="stacked_tip_region">' +region+ '</div><p><b>Exper. Code:</b> ' +exp+ '</p><p><b>' +value+ ' tickets</b></p><p><b>PRR:</b> ' +pct+ '%</p></div>');
        }, 0);
      }
      else {
        $("#stackedChart").on("focusout mouseleave mouseout", function(e) {
          setTimeout(function() {$('#stacked_prr').find('#stacked-tooltip').remove();}, 500);
        });
      }
    });
  }


  // GENERIC FUNCTIONS   @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @  @

  // TOP TABLE TOTAL ROW SUMS
  function topTableTotalRowSums(sum_all_tot,sum_all_prr,sum_all_proa_pf,sum_all_proa,sum_all_km,sum_all_al,sum_all_pf_lh) {

    var all_prr_p = 0, all_proa_p = 0, s_all_dr = 0, all_dr_p = 0, all_km_p = 0, all_al_p = 0;

    $("#tt-foot tfoot").find('tr').find(".td-all-totl").text(sum_all_tot);

    if ( parseInt(sum_all_prr) != 0 ) { avg_all_hrs_pf = (Math.round(sum_all_pf_lh / sum_all_prr *100)/100); } else { avg_all_hrs_pf = 0 ; }
    $("#tt-foot tfoot").find('tr').find(".td-all-avg-hrs-pf").text(avg_all_hrs_pf);

    $("#tt-foot tfoot").find('tr').find(".td-all-ph-c").text(sum_all_prr);
    if ( parseInt(sum_all_tot) != 0 ) { all_prr_p = (Math.round(sum_all_prr / sum_all_tot *100 *10)/10); } else { all_prr_p = 0 ; }
    $("#tt-foot tfoot").find('tr').find(".td-all-ph-p").text(all_prr_p+" %");
    $("#tt-foot tfoot").find('tr').find(".td-all-ph-p").attr('data-prr',all_prr_p);

    s_all_dr = sum_all_tot - sum_all_prr;
    $("#tt-foot tfoot").find('tr').find(".td-all-pr-c").text(sum_all_proa_pf);
    if ( parseInt(sum_all_proa) != 0 ) { all_proa_p = (Math.round(sum_all_proa_pf / sum_all_proa *100 *10)/10); } else { all_proa_p = 0 ; }
    $("#tt-foot tfoot").find('tr').find(".td-all-pr-p").text(all_proa_p+" %");
    $("#tt-foot tfoot").find('tr').find(".td-all-pr-p").attr('data-pprr',all_proa_p);

    //s_all_dr = sum_all_tot - sum_all_prr;
    $("#tt-foot tfoot").find('tr').find(".td-all-dr-c").text(s_all_dr);
    if ( parseInt(sum_all_tot) != 0 ) { all_dr_p = (Math.round((sum_all_tot - sum_all_prr) / sum_all_tot *100 *10)/10); } else { all_dr_p = 0 ; }
    $("#tt-foot tfoot").find('tr').find(".td-all-dr-p").text(all_dr_p+" %");

    $("#tt-foot tfoot").find('tr').find(".td-all-km-c").text(sum_all_km);
    if ( parseInt(sum_all_tot) != 0 ) { all_km_p = (Math.round(sum_all_km / sum_all_tot *100 *10)/10); } else { all_km_p = 0 ; }
    $("#tt-foot tfoot").find('tr').find(".td-all-km-p").text(all_km_p+" %");

    $("#tt-foot tfoot").find('tr').find(".td-all-al-c").text(sum_all_al);
    if ( parseInt(sum_all_tot) != 0 ) { all_al_p = (Math.round(sum_all_al / sum_all_tot *100 *10)/10); } else { all_al_p = 0 ; }
    $("#tt-foot tfoot").find('tr').find(".td-all-al-p").text(all_al_p+" %");
  }

  // BUILD TOP TABLE
  function buildTopTable(source,checked_pls) {

    var minperiod = $('#jquery_data #period-min').val();
    var maxperiod = $('#jquery_data #period-max').val();

    var s_dr = ""; //s_all_dr = "";
    //all_prr_p = 0, all_proa_p = 0, all_dr_p = 0, all_km_p = 0, all_al_p = 0;
    var sum_tot = 0, sum_prr = 0, sum_proa = 0, sum_dr = 0, sum_km = 0, sum_al = 0, sum_proa_pf = 0;
    var hrs_pf = 0, sum_pf_lh = 0, sum_all_pf_lh = 0;
    var sum_all_tot = 0, sum_all_prr = 0, sum_all_proa = 0, sum_all_dr = 0, sum_all_km = 0, sum_all_al = 0, sum_all_proa_pf = 0;
    var i0 = "", i1 = "", fail0 = 0, fail1 = 0;
    var table_row_html = "";
    toperr = [];  var v2 = ""; var d2 = ""; var v_full = "";

    // source = top | trend | pg | donut | init

    $.each(display_order, function(d, v){
      if ( report_type == "serv_perf" && sub_type == "cntr" ) { v_full = v; v = v.slice(0,v.indexOf("::")); }

      if ( report_type == "serv_perf" && source == "init" ) {
        try{ i0 = data.entity_pl.total_calls[v]; }catch(e){ if(e){ fail0 = 1; }}
      }
      else {
        try{ i0 = data.entity.total_calls[v]; }catch(e){ if(e){ fail0 = 1; }}
      }
      if ( fail0 == 0 ) { if (i0) {  // if entity has more than 0 total calls
        // each pl
        if ( report_type == "serv_perf" && minperiod == maxperiod ) {
          var this_per = minperiod;
          $.each(checked_pls, function(i, j){   // just one pl in this area, checked_pls[0]
            try{ i1 = data.entity_pl_period.total_calls[v][j][this_per]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_tot += i1; sum_all_tot += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl_period.phone_fix[v][j][this_per]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_prr += i1; sum_all_prr += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl_period.proa[v][j][this_per]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_proa += i1; sum_all_proa += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl_period.site_visit[v][j][this_per]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_dr += i1; sum_all_dr += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl_period.km_used[v][j][this_per]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_km += i1; sum_all_km += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl_period.al_used[v][j][this_per]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_al += i1; sum_all_al += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl_period.proa_pf[v][j][this_per]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_proa_pf += i1; sum_all_proa_pf += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = parseFloat(data.entity_pl_period.pf_labor_hours[v][j][this_per]); }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) {
            if ( typeof i1 === 'undefined' ) { i1 = 0; }
            sum_pf_lh += i1; sum_all_pf_lh += i1;
            } }
            fail1 = 0; i1 = "";
          });
        }
        else if ( report_type == "serv_perf" ) {
          $.each(checked_pls, function(i, j){
            try{ i1 = data.entity_pl.total_calls[v][j]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_tot += i1; sum_all_tot += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl.phone_fix[v][j]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_prr += i1; sum_all_prr += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl.proa[v][j]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_proa += i1; sum_all_proa += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl.site_visit[v][j]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_dr += i1; sum_all_dr += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl.km_used[v][j]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_km += i1; sum_all_km += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl.al_used[v][j]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_al += i1; sum_all_al += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = data.entity_pl.proa_pf[v][j]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_proa_pf += i1; sum_all_proa_pf += i1; } }
            fail1 = 0; i1 = "";
            try{ i1 = parseFloat(data.entity_pl.pf_labor_hours[v][j]); }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) {
            if ( typeof i1 === 'undefined' ) { i1 = 0; }
            sum_pf_lh += i1; sum_all_pf_lh += i1;
            } }
            fail1 = 0; i1 = "";
          });
        }
        else {
          try{ i1 = data.entity.total_calls[v]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_tot += i1; sum_all_tot += i1; } }
          fail1 = 0; i1 = "";
          try{ i1 = data.entity.phone_fix[v]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_prr += i1; sum_all_prr += i1; } }
          fail1 = 0; i1 = "";
          try{ i1 = data.entity.proa[v]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_proa += i1; sum_all_proa += i1; } }
          fail1 = 0; i1 = "";
          try{ i1 = data.entity.site_visit[v]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_dr += i1; sum_all_dr += i1; } }
          fail1 = 0; i1 = "";
          try{ i1 = data.entity.km_used[v]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_km += i1; sum_all_km += i1; } }
          fail1 = 0; i1 = "";
          try{ i1 = data.entity.al_used[v]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_al += i1; sum_all_al += i1; } }
          fail1 = 0; i1 = "";
          try{ i1 = data.entity.proa_pf[v]; }catch(e){ if(e){ fail1 = 1; }} if ( fail1 == 0 ) { if (i1) { sum_proa_pf += i1; sum_all_proa_pf += i1; } }
          fail1 = 0; i1 = "";
        }
        if ( fail0 == 0 ) { if (i0) {  // if entity has more than 0 total calls
          table_row_html = topTableMakeRows(report_type,sub_type,toperr,table_row_html,v,v_full,d,sum_tot,sum_proa,sum_proa_pf,sum_prr,sum_km,sum_al,s_dr,hrs_pf,sum_pf_lh);
        }}
      }}
      i0 = ""; i1 = ""; fail0 = 0; fail1 = 0;
      s_dr = "";
      sum_tot = 0; sum_prr = 0; sum_proa = 0; sum_dr = 0; sum_km = 0; sum_al = 0; sum_proa_pf = 0; hrs_pf = 0; sum_pf_lh = 0;
    });

    var dt = $( "#tt" ).DataTable();
    $("#tt_re").empty();
    dt.destroy();

    if ( report_type == "prr_ex" ) {
      toperr.sort(function(a,b) { return a.v - b.v; });
      toperr.reverse();
      toperr = toperr.slice(0, 10);
    }

    $('#tt_re').html(regenerateDataTableHTML);
    $("#tt tbody").append(table_row_html);
    $('#tt').html(recreateDataTable);

    topTableTotalRowSums(sum_all_tot,sum_all_prr,sum_all_proa_pf,sum_all_proa,sum_all_km,sum_all_al,sum_all_pf_lh);
  }


  // TOP TABLE MAKE ROWS
  function topTableMakeRows(report_type,sub_type,toperr,table_row_html,v,v_full,d,sum_tot,sum_proa,sum_proa_pf,sum_prr,sum_km,sum_al,s_dr,hrs_pf,sum_pf_lh) {

    var prr_p = 0, proa_p = 0, dr_p = 0, km_p = 0, al_p = 0;

    if ( parseInt(sum_tot) != 0 ) { prr_p = (Math.round(sum_prr / sum_tot *100 *10)/10); } else { prr_p = 0 ; }
    if ( report_type != "prr_ex" ) { if ( parseInt(sum_prr) != 0 ) { hrs_pf = (Math.round(sum_pf_lh / sum_prr *100)/100); } else { hrs_pf = 0 ; } }
    if ( parseInt(sum_proa) != 0 ) { proa_p = (Math.round(sum_proa_pf / sum_proa *100 *10)/10); } else { proa_p = 0 ; }
    s_dr = sum_tot - sum_prr;
    if ( parseInt(sum_tot) != 0 ) { dr_p = (Math.round((sum_tot - sum_prr) / sum_tot *100 *10)/10); } else { dr_p = 0 ; }
    if ( parseInt(sum_tot) != 0 ) { km_p = (Math.round(sum_km / sum_tot *100 *10)/10); } else { km_p = 0 ; }
    if ( parseInt(sum_tot) != 0 ) { al_p = (Math.round(sum_al / sum_tot *100 *10)/10); } else { al_p = 0 ; }

    if ( report_type == "serv_perf" ) {
      if ( sub_type == "ctry" ) {
        table_row_html += '<tr data-cc="'+v+'">';
        table_row_html += '<td class="td-ctry">'+d+'</td>'; // c_desc
      }
      else if ( sub_type == "ind" ) {
        table_row_html += '<tr data-cc="'+v+'::'+d+'">';
        table_row_html += '<td class="td-ctry sub_type_ind">'+d+' ('+v.toUpperCase()+')</td>'; // c_desc
      }
      else {  // cntr
        var v_name = v_full.slice(v_full.indexOf("::") +2);
        table_row_html += '<tr data-cc="'+v_full+'">';
        table_row_html += '<td class="td-ctry sub_type_cntr">'+v_name+'</td>'; // c_desc
      }
    }
    else if ( report_type == "prr_ex" ) {
      var v2 = v.indexOf(":");
      var d2 = v.slice(v2 +1);
      v2 = v.slice(0,v2);
      table_row_html += '<tr data-exp="'+v+'">';
      table_row_html += '<td class="td-exp report_type_prr"><b>'+v2+':</b> '+d2+'</td>'; // c_desc
      toperr.push({ i: v, v: sum_tot });
    }

    // TOTAL TICKETS
    table_row_html += '<td class="td-totl">'+sum_tot+'</td>';
    // HOURS PER PHONE FIX
    if ( report_type != "prr_ex" ) { table_row_html += '<td class="td-hr-c">'+hrs_pf+'</td>'; }
    // PHONE RESOLVED
    table_row_html += '<td class="td-ph-c">'+sum_prr+'</td>';
    table_row_html += '<td class="td-ph-p percent green" data-prr="'+prr_p+'">'+prr_p+' %</td>';
    // TICKETS DISPATCHED
    table_row_html += '<td class="td-dr-c">'+s_dr+'</td>';
    table_row_html += '<td class="td-dr-p percent red" data-dis="'+dr_p+'">'+dr_p+' %</td>';
    // PROACTIVE TICKETS
    table_row_html += '<td class="td-pr-c">'+sum_proa_pf+'</td>';
    table_row_html += '<td class="td-pr-p percent yellow" data-pprr="'+proa_p+'">'+proa_p+' %</td>';
    // KM Utilized
    table_row_html += '<td class="td-km-c"> '+sum_km+' </td>';
    table_row_html += '<td class="td-km-p percent blue" data-km_rate="'+km_p+'">'+km_p+' %</td>';
    // AL Utilized
    table_row_html += '<td class="td-al-c"> '+sum_al+' </td>';
    table_row_html += '<td class="td-al-p percent orange" data-al_rate="'+al_p+'">'+al_p+' %</td>';
    table_row_html += '</tr>';

    return table_row_html;
  }

  // TOP TABLE HIGHLIGHTING
  function ttHighlight(cc) {
    if ( report_type == "serv_perf" ) {
      if ( cc == "000" ) {
        $(".dataTables_scrollFoot td:first-child").addClass("selected-cc");
        $(".dataTables_scrollFoot td:first-child").css("color", "black");
      }
      else {
        $(".dataTables_scrollBody>table>tbody").find("tr[data-cc='"+cc+"']").find("td:first-child").addClass("selected-cc");
        $(".dataTables_scrollBody>table>tbody").find("tr[data-cc='"+cc+"']").addClass("selected-row");
      }
    }
    else {  // prr_ex
      if ( cc == "000" ) {
        $(".dataTables_scrollFoot td:first-child").addClass("selected-exp");
        $(".dataTables_scrollFoot td:first-child").css("color", "black");
      }
      else {
        $(".dataTables_scrollBody>table>tbody").find("tr[data-exp='"+cc+"']").find("td:first-child").addClass("selected-exp");
        $(".dataTables_scrollBody>table>tbody").find("tr[data-exp='"+cc+"']").addClass("selected-row");
      }
    }
  }

  // TREND COUNTS
  function trendCounts(cc,checked_pls,per,sum_tot,sum_prr,sum_proa,sum_dr,sum_km,sum_al,sum_proa_pf) {
    var i0 = "", fail0 = 0;
    $.each(checked_pls, function(i, v){
      $.each( per, function(i, n) {
        if ( cc == "000" ) {
          // barring a universal jquery test for existence ('IF var[1][2] EXISTS'), each array object is first tested ('try{}') to see if set
          try{ i0 = data.total_pl_period.total_calls[v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_tot[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.total_pl_period.phone_fix[v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_prr[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.total_pl_period.proa[v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_proa[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.total_pl_period.site_visit[v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_dr[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.total_pl_period.km_used[v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_km[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.total_pl_period.al_used[v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_al[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.total_pl_period.proa_pf[v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_proa_pf[n] += i0; } }
          fail0 = 0; i0 = "";
        }
        else {
          try{ i0 = data.entity_pl_period.total_calls[cc][v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_tot[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.entity_pl_period.phone_fix[cc][v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_prr[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.entity_pl_period.proa[cc][v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_proa[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.entity_pl_period.site_visit[cc][v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_dr[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.entity_pl_period.km_used[cc][v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_km[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.entity_pl_period.al_used[cc][v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_al[n] += i0; } }
          fail0 = 0; i0 = "";
          try{ i0 = data.entity_pl_period.proa_pf[cc][v][n]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { sum_proa_pf[n] += i0; } }
          fail0 = 0; i0 = "";
        }
      });
    });
  }

  // BUILD TREND CHART
  function buildTrendChart(source,checked_pl_groups_all,notInit,cc,per,period_idx) {
    sum_tot = []; sum_prr = []; sum_proa = []; sum_dr = []; sum_km = []; sum_al = []; sum_proa_pf = [];
    $.each( per, function(i, n) { sum_tot[n] = 0; sum_prr[n] = 0; sum_proa[n] = 0; sum_dr[n] = 0; sum_km[n] = 0; sum_al[n] = 0; sum_proa_pf[n] = 0; });
    fail0 = 0; i0 = "";

    if ( source != "init" ) {
      // make cc consistent
      if ( report_type == "serv_perf" ) {
        var cc_full = cc;  // preserves original data item, in case where cc var is subsequently processed
        if ( cc != "000" && ( sub_type == "ind" || sub_type == "cntr" ) ) { cc = cc.slice(0,cc.indexOf("::")); }
      }
      var notInit = 1;
    }
    if ( ( checked_pl_groups_all != 1 && ( source == "top" || source == "pg" ) ) || source == "trend" || source == "donut" ) {
      // meaning not ALL PLs, but some PLs only, thus sum up PLs by Period
      trendCounts(cc,checked_pls,per,sum_tot,sum_prr,sum_proa,sum_dr,sum_km,sum_al,sum_proa_pf);
    }

    var trend_x_label_cc = "", trend_data_prr_cc = "", trend_data_proa_cc = "", trend_data_dr_cc = "", trend_data_km_cc = "", trend_data_al_cc = "";
    $.each( per, function(i, n) {
      trend_x_label_cc += "'"+n+"',";
      if ( ( checked_pl_groups_all == 1 && ( source == "top" || source == "pg" ) ) || source == "init" ) { // else, prior variable settings prevail
        if ( cc == "000" ) {  // ALL countries
          try{ i0 = data.total_period.total_calls[n]; }catch(e){ if(e){ fail0 = 1; }}
          if ( fail0 == 0 ) {
            if (i0) {
              sum_tot[n] = data.total_period.total_calls[n];
              sum_prr[n] = data.total_period.phone_fix[n];
              sum_proa[n] = data.total_period.proa[n];
              sum_dr[n] = data.total_period.site_visit[n];
              sum_km[n] = data.total_period.km_used[n];
              sum_al[n] = data.total_period.al_used[n];
              sum_proa_pf[n] = data.total_period.proa_pf[n];
            }
          }
        }
        else {
          try{ i0 = data.entity_period.total_calls[cc][n]; }catch(e){ if(e){ fail0 = 1; }}
          if ( fail0 == 0 ) {
            if (i0) {
              sum_tot[n] = data.entity_period.total_calls[cc][n];
              sum_prr[n] = data.entity_period.phone_fix[cc][n];
              sum_proa[n] = data.entity_period.proa[cc][n];
              sum_dr[n] = data.entity_period.site_visit[cc][n];
              sum_km[n] = data.entity_period.km_used[cc][n];
              sum_al[n] = data.entity_period.al_used[cc][n];
              sum_proa_pf[n] = data.entity_period.proa_pf[cc][n];
            }
          }
        }
      }

      // for correct rounding to 1 decimal place in js, use "*10)/10": https://www.kirupa.com/html5/rounding_numbers_in_javascript.htm
      if ( parseInt(sum_tot[n]) != 0 ) { trend_data_prr_cc += (Math.round(sum_prr[n] / sum_tot[n] *100 *10)/10)+","; }
      else { trend_data_prr_cc += (",") ; }

      if ( parseInt(sum_proa[n]) != 0 ) { trend_data_proa_cc += (Math.round(sum_proa_pf[n] / sum_proa[n] *100 *10)/10)+","; }
      else { trend_data_proa_cc += (",") ; }

      if ( parseInt(sum_tot[n]) != 0 ) { trend_data_dr_cc += (Math.round((sum_tot[n] - sum_prr[n]) / sum_tot[n] *100 *10)/10)+","; }
      else { trend_data_dr_cc += (",") ; }

      if ( parseInt(sum_tot[n]) != 0 ) { trend_data_km_cc += (Math.round(sum_km[n] / sum_tot[n] *100 *10)/10)+","; }
      else { trend_data_km_cc += (",") ; }

      if ( parseInt(sum_tot[n]) != 0 ) { trend_data_al_cc += (Math.round(sum_al[n] / sum_tot[n] *100 *10)/10)+","; }
      else { trend_data_al_cc += (",") ; }
    });

    trend_x_label_cc = trend_x_label_cc.slice(0,-1);
    var cid_c_label = trend_x_label_cc.replace(/\'/g,"-");
    var cid_c_prr = trend_data_prr_cc.slice(0,-1);
    var cid_c_proa = trend_data_proa_cc.slice(0,-1);
    var cid_c_dr = trend_data_dr_cc.slice(0,-1);
    var cid_c_km = trend_data_km_cc.slice(0,-1);
    var cid_c_al = trend_data_al_cc.slice(0,-1);

    var msie, // holds major version number for IE, or NaN if UA is not IE. Support: IE 9-11 only
    // documentMode is an IE-only property: http://msdn.microsoft.com/en-us/library/ie/cc196988(v=vs.85).aspx
    msie = window.document.documentMode;

    if ( msie <= 11 && source == "trend" ) {  // show month
      var thiss = this_per.slice(0,4)+"/"+this_per.slice(4);
      $('#all-trend-IE-is-special').html('SELECTED MONTH IS<br/>'+thiss); //201808
    }
    else {  // not IE, can destroy and rebuild
      if ( notInit == 1 || source != "init" ) {
      trendChart.destroy();
    }
    $(document).ready(function() {
      $("#trendChart").load("trending-chart_chart-js.php?index=" +period_idx+ "&label=" +cid_c_label+ "&prr=" +cid_c_prr+ "&dr=" +cid_c_dr+ "&proa=" +cid_c_proa+ "&km=" +cid_c_km+ "&al=" +cid_c_al);
    });

    }
    trendClick();
  }


  // BUILD DONUT CHART
  function buildDonutChart(source,cc,checked_pls) {
    var sum_tix = 0;
    var against = "", i0 = "", fail0 = 0;
    ctry_pl = []; pl_keys = ""; pl_vals = "";

    var minperiod = $('#jquery_data #period-min').val();
    var maxperiod = $('#jquery_data #period-max').val();

    if ( (minperiod == maxperiod && source != "init") || source == "trend" ) {
      var this_per = minperiod;
      if ( cc == "000" ) {  // ALL
        $.each( data.total_pl_period.total_calls, function(i, cc) {
          try{ i0 = data.total_pl_period.total_calls[i][this_per]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { against = i0; } }
          if ( jQuery.inArray(i, checked_pls) !== -1 ) { ctry_pl.push({ i: i, v: against }); if ( typeof against !== 'undefined' && against != "" ) { sum_tix = sum_tix + parseInt(against); } }
          against = "";
        });
      }
      else {
        $.each( data.entity_pl_period.total_calls[cc], function(i, v) {
          try{ i0 = data.entity_pl_period.total_calls[cc][i][this_per]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) { against = i0; } }
          fail0 = 0; i0 = "";
          if ( jQuery.inArray(i, checked_pls) !== -1 && against > 0 ) { ctry_pl.push({ i: i, v: against }); if ( typeof against !== 'undefined' && against != "" ) { sum_tix = sum_tix + parseInt(against); } }
          against = "";
        });
      }
    }
    else {
      if ( cc == "000" ) {  // ALL
        try{ i0 = data.total_pl.total_calls; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) {
          $.each( data.total_pl.total_calls, function(i, cc) {
            if ( jQuery.inArray(i, checked_pls) !== -1 ) { ctry_pl.push({ i: i, v: cc }); sum_tix = sum_tix + cc; }
          });
        }}
      }
      else {
        try{ i0 = data.entity_pl.total_calls[cc]; }catch(e){ if(e){ fail0 = 1; }} if ( fail0 == 0 ) { if (i0) {
          $.each( data.entity_pl.total_calls[cc], function(i, cc) {
            if ( jQuery.inArray(i, checked_pls) !== -1 ) { ctry_pl.push({ i: i, v: cc }); sum_tix = sum_tix + cc; }
          });
        }}
      }
    }
    var ctry_pl_by_value = ctry_pl.slice(0);  // use slice() to copy the array and not just make a reference
    ctry_pl_by_value.sort(function(a,b) { return a.v - b.v; });
    ctry_pl_by_value.reverse();

    var resetCanvas = function() {  // apparently, this extra code is needed to completely wipe and redraw donut chart, including click map, etc.
      $('#donutChart').remove();
      $('#result2').append('<canvas id="donutChart" width="600" height="300"></canvas>');
      canvas = document.querySelector('#donutChart');
    };

    if (ctry_pl_by_value.length !== 0) {  // if results
      var j = 0; var extra_slices = 0; var all_slices = 0; var extra_slices_list = "";
      $.each( ctry_pl_by_value, function(i, v) { all_slices = all_slices +v.v; }); // get a total count
      $.each( ctry_pl_by_value, function(i, v) {
        if ( v.v != "" ) {
          if ( j <= 23 ) {
            var keystuff = inst_data[v.i];
            if ( keystuff.length > 20 ) {
              var lastbracket = keystuff.lastIndexOf("(");
              var grabbrackets = keystuff.substring(lastbracket);
              keystuff = keystuff.substring(0,14)+ "..." +grabbrackets;
            }
            var vpct = (Math.round(v.v/all_slices *100 *10)/10);
            pl_keys += keystuff+": "+vpct+"%,";
            pl_vals += v.v+",";
          }
          else {
            var keystuff = inst_data[v.i];
            var lastbracket = keystuff.lastIndexOf("(") +1;
            var grabbrackets = keystuff.substring(lastbracket, keystuff.length -1);
            extra_slices_list += grabbrackets+",";  // save all PLs that are under the "Additional" designation on the donut
            extra_slices = extra_slices +v.v;
          }
          j = j +1;
        }
      });
      if ( j >= 24 ) {
        var extra_slices_pct = (Math.round(extra_slices/all_slices *100 *10)/10);
        j = j -24;
        pl_keys += "[ ADDITIONAL "+j+" PLs: "+extra_slices_pct+"% ],"; // add in last slices as one aggregate slice.
        pl_vals += extra_slices+",";
      }
      pl_keys = pl_keys.replace(/\ /g,"_");
      pl_keys = pl_keys.slice(0,-1);
      pl_vals = pl_vals.slice(0,-1);

      // checksum - post sum of slices (should match sum of tickets)
      $("#donut-checksum").html("<b>Ticket Sum:</b> "+all_slices);

      extra_slices_list = extra_slices_list.slice(0,-1);
      $("#jquery_data #extra-slices-list").remove();
      $("#jquery_data .h-slice").append('<input type="hidden" id="extra-slices-list" name="extra-slices-list" value="'+extra_slices_list+'">');

      if ( source == "pg" ) {
        var current_slice_pl = ""; // on click of product group, regardless of whether single pl was selected prior, interface resets to show "multiple Selected", so need to wipe pl hilite
      }
      else {
        var current_slice_pl = $('#jquery_data #current-slice-pl').val();
      }

      $(".no-results").text(''); $(".no-results").removeClass('no-results-on');
      resetCanvas();
      if ( notInit == 1 || source != "init" ) { donutChart.destroy(); }
      $(document).ready(function() {
        $('#donutChart').load('donut-chart_chart-js.php?plkeys=' +pl_keys+ '&plvals=' +pl_vals+ '&sumtix=' +sum_tix+ '&hilite=' +current_slice_pl);
      });
    }
    else {
      resetCanvas();
      if ( notInit == 1 || source != "init" ) { donutChart.destroy(); }
      $(".no-results").text('No results.'); $(".no-results").addClass('no-results-on');
    }
    donutClick();
  }


  function updateBottomTable( pl_groups_list,checked_pls, pls_list, country_in, from_widget ) {

    switch(from_widget){
      case "doughnut_chart":
        var all_pl_ct = <?php echo $all_pl_ct; ?>;

        if ( checked_pls.length >= all_pl_ct ) { pls_list = "000"; }
        // slight count discrepancy may be due to occasional new pl number residing under category: "undetermined". may in the future do a precise count by excluding this category.

        pls_url = pls_list.replace(/\'/g, '');
        pls_array = pls_url.split(",");
        request.pl = pls_array;
      break;

      case "trend_chart":

        request.di_min = $('#jquery_data #period-min').val();
        request.di_max = $('#jquery_data #period-max').val();
        request.minperiod = request.di_min;
        request.maxperiod = request.di_max;
      break;

      case "top_table":
        // By Location
        // This only changes the country selected  (country)
        if ( sub_type == "ctry" ) {
          if ( orig_country_request != "000" ) {  // LOGIC: When selecting TOTAL in top table, if one country exists in top table, revert to that country. Lack of where cause causing memory overflow error -- look at underlying issue when have time later.
            request.country = orig_country_request;
          }
          else {
            request.country = c_code_bottom_table;
          }
        }
        // by handled by
        else if ( sub_type == "ind" ) {
          if(c_code_bottom_table != "000") {
            request.ind_hand = [];
            request.ind_hand[0] = c_code_bottom_table;
          }
          else if ( typeof orig_ind_request !== "undefined" ) {
            request.ind_hand = orig_ind_request;
          }
          else {
            delete(request.ind_hand);
          }
        }
        // by call center
        else {
          if(c_code_bottom_table != "000") {
            request.centers = [];
            request.centers[0] = c_code_bottom_table;
          }
          else if ( typeof orig_center_request !== "undefined" ) {
            request.centers = orig_center_request;
          }
          else {
            delete(request.centers);
          }
        }
      break;

      case "top_table_exp":
        // This only changes the exp code selected
        request.exp = e_code_bottom_table;
      break;

      case "viewer":
        request.di_min = $('#jquery_di_data #di_min', window.parent.document).val();
        request.di_max = $('#jquery_di_data #di_max', window.parent.document).val();
        //request.di_min = $('#jquery_data #period-min').val();
        //request.di_max = $('#jquery_data #period-max').val();

        request.minperiod = request.di_min;
        request.maxperiod = request.di_max;
      break;
    }

    // UPDATE BOTTOM TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    if ( report_type == "serv_perf" ) {
      // footer total selected?
      var found_selected = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr >  td.ui-state-default.limit-width.selected-cc").html();
      // footer total amount
      var this_is_the = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.td-all-totl").html();
    }
    else { // report_type = prr_ex
      var found_selected = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.selected-exp").html();
      var this_is_the = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.td-all-totl").html();
    }

    if ( typeof found_selected !== 'undefined' && this_is_the >= 10000 ) {  // threshold to display bottom table
      $("#bt_re").html("<div class=\"bottom-table-noselect-text\">please make a selection above</div>");
      //$("#content-wrap").removeClass("content-freeze");
    }
    else {

      $.ajax({url: 'bottom-table_datatables-js.php', data: request,  async:true, success: function(result){
        $("#bt_re").html(result);
      }});

      if ( is_IE == 0 ) { $("#bt_re").append("<div class=\"bottom-table-throbber\"><img src=\"graphics/verification_spinner.gif\"></div>"); }

    }
    $("#bt_re").removeClass("blank");

  }

  // UPDATE METERS
  function updateMeter1(cc) {
    if ( cc == "000" ) { var cid_a = $("#tt-foot tfoot").find("tr").find("td.green_total").data("prr"); }
    else { var cid_a = $("#tt tbody").find('tr[data-cc="'+cc+'"]').find("td.green").data("prr");  }
    if ( cid_a === undefined ) { cid_a = 0; }
    $(".PRR-meter").attr("src", "overall_meter.php?value=" +cid_a+ "&title=Phone Resolution Rate&color=0x82c341");
  }

  function updateMeter2(cc) {
    if ( cc == "000" ) {  var cid_b = $("#tt-foot tfoot").find("tr").find("td.yellow_total").data("pprr"); }
    else { var cid_b = $("#tt tbody").find('tr[data-cc="'+cc+'"]').find("td.yellow").data("pprr");  }
    if ( cid_b === undefined ) { cid_b = 0; }
    $(".PPRR-meter").attr("src", "overall_meter.php?value=" +cid_b+ "&title=Proactive Phone Resolution Rate&color=0xf2c80f");
  }

  // REDRAW ROW ZERO SUMMARY OF SELECTIONS
  function currentSummation() {

    if ( report_type == "serv_perf" ) { var report_type_text = "Service Performance"; }
    else { var report_type_text = "PRR By Exp Code"; }
    var summ_report_type = "<span class=\"summ_title\">Report Type: &nbsp;</span><span class=\"summ_value\">"+report_type_text+"</span>";

    if ( sub_type == "ctry" ) { var sub_report_type_text = "by Location"; }
    else if ( sub_type == "ind" ) { var sub_report_type_text = "by Individual"; }
    else { var sub_report_type_text = "by Call/Smart Center"; }
    var summ_sub_report_type = " - <span class=\"summ_value\">"+sub_report_type_text+"</span>";

    var minperiod = $('#jquery_data #period-min').val();
    var maxperiod = $('#jquery_data #period-max').val();

    if ( minperiod == maxperiod ) { var purtys = minperiod;  var date_range = "Date"; }
    else { var purtys = minperiod+ " to " +maxperiod; var date_range = "Date Range"; }
    var summ_date = ", &nbsp; <span class=\"summ_title\">"+date_range+": &nbsp;</span><span class=\"summ_value\">"+purtys+"</span>";

    if ( top_menu_button_desc_org == " " ) {
      top_menu_button_desc_org = org_data.org_desc;
      var colon = top_menu_button_desc_org.lastIndexOf(":") +1; // for labeling consistency, chop off everything prior to the colon
      top_menu_button_desc_org = top_menu_button_desc_org.substring(colon,top_menu_button_desc_org.length);
      if ( top_menu_button_desc_org.slice(0,1) == " " ) { top_menu_button_desc_org = top_menu_button_desc_org.slice(1,top_menu_button_desc_org.length); } // trim not working, hence this
    }
    var summ_org_desc = " , &nbsp; <span class=\"summ_title\">Organization: &nbsp;</span><span class=\"summ_value\">"+top_menu_button_desc_org+"</span>";

    if ( top_menu_button_desc_instr == " " ) { top_menu_button_desc_instr = $("#viewer_form", window.parent.document).find(".gsr_top_selectors_tb>tbody tr:eq(0) td:eq(0) span").text(); }
    var frb = top_menu_button_desc_instr.indexOf(")") +1;
    var srb = top_menu_button_desc_instr.indexOf(")", frb) +1;
    if ( frb != 0 && srb != 0 ) { top_menu_button_desc_instr = top_menu_button_desc_instr.substring(0,frb)+", "+top_menu_button_desc_instr.substring(frb); }

    if ( top_menu_button_desc_instr == " " ) {
      if ( donut_plgs != "000" ) {
        ct_plg = donut_plgs.length;
        if ( ct_plg == 1 || ct_plg == 2 ) {
          $.each(donut_plgs, function(i, v){
            top_menu_button_desc_instr += " " +donut_plgs[i]+ " Family, ";
          });
          top_menu_button_desc_instr = top_menu_button_desc_instr.slice(0,-2);
        }
        else if ( ct_plg > 2 ) {
          top_menu_button_desc_instr = "Multiple Selected";
        }
      }
      else {  // if donut chart fails to present selector data, due to page refresh (submit), top button overrides -- grab descriptor from top menu button
        top_menu_button_desc_instr = $("#viewer_form", window.parent.document).find(".gsr_top_selectors_tb>tbody tr:eq(0) td:eq(0) span").text();
        // drop in comma, in cases of two instruments
        var count_bracket = top_menu_button_desc_instr.replace(/[^)]/g, "").length;
        if ( count_bracket == 2 ) { top_menu_button_desc_instr = top_menu_button_desc_instr.replace(/\)/,"), "); }
      }
    }

    var summ_is_in = top_menu_button_desc_instr;
    var summ_is_sg = $("#viewer_form", window.parent.document).find(".gsr_top_selectors_tb>tbody tr:eq(0) td:eq(2) span").text();
    var summ_is_bs = $("#viewer_form", window.parent.document).find(".gsr_top_selectors_tb>tbody tr:eq(0) td:eq(3) span").text();

    if ( summ_is_in != "" ) { var summ_in = " , &nbsp; <span class=\"summ_title\">Instruments: &nbsp;</span><span class=\"summ_value\">"+summ_is_in+"</span>"; }
    else { var summ_in = ""; }
    if ( summ_is_sg != "" ) { var summ_sg = " , &nbsp; <span class=\"summ_title\">Servicing Groups: &nbsp;</span><span class=\"summ_value\">"+summ_is_sg+"</span>"; }
    else { var summ_sg = ""; }
    if ( summ_is_bs != "" ) { var summ_bs = " , &nbsp; <span class=\"summ_title\">Business Segments: &nbsp;</span><span class=\"summ_value\">"+summ_is_bs+"</span>"; }
    else { var summ_bs = ""; }

    // left menu options
    if ( typeof request.comp !== 'undefined' || typeof request.sd !== 'undefined' || typeof request.sp !== 'undefined' || typeof request.inq !== 'undefined' ) {
      var summ_ticket_types = " , &nbsp; <span class=\"summ_title\">Ticket Types: &nbsp;</span><span class=\"summ_value\">";
      if ( typeof request.comp !== 'undefined' ) { summ_ticket_types += "Complaint, "; }
      if ( typeof request.sd !== 'undefined' ) { summ_ticket_types += "Service Demand, "; }
      if ( typeof request.sp !== 'undefined' ) { summ_ticket_types += "Service Planned, "; }
      if ( typeof request.inq !== 'undefined' ) { summ_ticket_types += "Inquiry, "; }
      summ_ticket_types = summ_ticket_types.slice(0,-2);
      summ_ticket_types += "</span>";
    }
    else { var summ_ticket_types = ""; }

    if ( typeof request.t_clsd !== 'undefined' || typeof request.t_open !== 'undefined' || typeof request.t_void !== 'undefined' ) {
      var summ_ticket_status = " , &nbsp; <span class=\"summ_title\">Ticket Status: &nbsp;</span><span class=\"summ_value\">";
      if ( typeof request.t_clsd !== 'undefined' ) { summ_ticket_status += "Closed, "; }
      if ( typeof request.t_open !== 'undefined' ) { summ_ticket_status += "Open, "; }
      if ( typeof request.t_void !== 'undefined' ) { summ_ticket_status += "Void, "; }
      summ_ticket_status = summ_ticket_status.slice(0,-2);
      summ_ticket_status += "</span>";
    }
    else { var summ_ticket_status = ""; }

    if ( typeof request.by_oper !== 'undefined' || typeof request.by_phm !== 'undefined' || typeof request.by_tso !== 'undefined' ) {
      var summ_ticket_opened_by = " , &nbsp; <span class=\"summ_title\">Ticket Opened By: &nbsp;</span><span class=\"summ_value\">";
      if ( typeof request.by_oper !== 'undefined' ) { summ_ticket_opened_by += "Operator, "; }
      if ( typeof request.by_phm !== 'undefined' ) { summ_ticket_opened_by += "PHM/POM, "; }
      if ( typeof request.by_tso !== 'undefined' ) { summ_ticket_opened_by += "TSO, "; }
      summ_ticket_opened_by = summ_ticket_opened_by.slice(0,-2);
      summ_ticket_opened_by += "</span>";
    }
    else { var summ_ticket_opened_by = ""; }

    if ( typeof request.no_parts !== 'undefined' ) {
      var summ_ticket_no_parts = " , &nbsp; <span class=\"summ_title\">Limit Tickets To: &nbsp;</span><span class=\"summ_value\">No Parts Replaced</span>";
    }
    else { var summ_ticket_no_parts = ""; }

    if ( typeof request.ind_hand !== 'undefined' && sub_type == "ind" ) {
      var summ_ticket_ind_hand = " , &nbsp; <span class=\"summ_title\">Ticket Handled By: &nbsp;</span><span class=\"summ_value\">";
      $.each(request.ind_hand, function(i, v){
        var indiv = v.substring(0, v.indexOf(':'));
        summ_ticket_ind_hand += indiv+ ", ";
      });
      summ_ticket_ind_hand = summ_ticket_ind_hand.slice(0,-2);
      summ_ticket_ind_hand += "</span>";
    }
    else { var summ_ticket_ind_hand = ""; }

    if ( typeof request.centers !== 'undefined' && sub_type == "cntr" ) {
      var summ_ticket_centers = " , &nbsp; <span class=\"summ_title\">Call/Smart Centers: &nbsp;</span><span class=\"summ_value\">";
      $.each(request.centers, function(i, v){
        var center = v.substring(v.indexOf('::') +2);
        summ_ticket_centers += center+ ", ";
      });
      summ_ticket_centers = summ_ticket_centers.slice(0,-2);
      summ_ticket_centers += "</span>";
    }
    else { var summ_ticket_centers = ""; }

    $(this).html("<span class=\"summ_title\"><b>SELECTIONS -- </b></span>"+summ_report_type+summ_sub_report_type+summ_org_desc+summ_date+summ_in +summ_sg +summ_bs+summ_ticket_types+summ_ticket_status+summ_ticket_opened_by+summ_ticket_no_parts+summ_ticket_ind_hand+summ_ticket_centers);
  }
  $('#summation').html(currentSummation);

  // REGENERATE DATATABLE HTML
  if ( report_type == "serv_perf" ) {
    if ( sub_type == "ctry" ) { var entity_label = "Location"; }
    else if ( sub_type == "ind" ) { var entity_label = "Individual"; }
    else { var entity_label = "Call Center"; }
  }
  else {
    { var entity_label = "Experience Code"; }
  }

  function regenerateDataTableHTML() {
    if ( report_type != "prr_ex" ) { var phone_fix_header = "<th>Avg. Hrs./<br/>Phone Fix</th>"; var phone_fix_footer = "<td class=\"td-all-avg-hrs-pf\"></td>"; }
    else { var phone_fix_header = ""; var phone_fix_footer = ""; }

    $('#tt_re').append('<table id="tt" class="display compact" style="width:100%"><div class="table-header-text1">Sort by clicking column titles</div><div id="top_chart_csv_icon"><img src="/gsr/common/images/icons/csv_small.gif"><span>Export to CSV File</span></div><thead><tr><th>'+entity_label+'</th><th># Total<br/> Tickets</th>'+phone_fix_header+'<th># Phone<br/> Resolved</th><th>PRR</th><th># Tickets<br/> Dispatched</th><th>Dispatch<br/> Rate</th><th># Proactive<br/> (PF) Tickets</th><th>PRR<br/> Proactive</th><th># Tickets<br/> w/ KM Use</th><th>KM Utiliz.<br/> Rate</th><th># Tickets<br/> w/ AL Use</th><th>AL Utiliz.<br/> Rate</th></tr></thead><tbody></tbody><tfoot><tr><td>Total</td><td class="td-all-totl"></td>'+phone_fix_footer+'<td class="td-all-ph-c"></td><td class="td-all-ph-p" data-prr=""></td><td class="td-all-dr-c"></td><td class="td-all-dr-p"></td><td class="td-all-pr-c"></td><td class="td-all-pr-p" data-pprr=""></td><td class="td-all-km-c"></td><td class="td-all-km-p"></td><td class="td-all-al-c"></td><td class="td-all-al-p"></td></tr></tfoot></table>');
  }

  // RECREATE DATATABLE
  function recreateDataTable() {
    if ( report_type == "serv_perf" ) { //  extra field in service performance report
      $('#tt').DataTable( {
        "columns":[
        { "name":"c00", "className": "limit-width"},
        { "name":"c01", "className": "dt-body-right"},
        { "name":"c02", "className": "dt-body-right"},
        { "name":"c03", "className": "dt-body-right"},
        { "name":"c04", "className": "dt-body-right"},
        { "name":"c05", "className": "dt-body-right"},
        { "name":"c06", "className": "dt-body-right"},
        { "name":"c07", "className": "dt-body-right"},
        { "name":"c08", "className": "dt-body-right"},
        { "name":"c09", "className": "dt-body-right"},
        { "name":"c10", "className": "dt-body-right"},
        { "name":"c11", "className": "dt-body-right"},
        { "name":"c12", "className": "dt-body-right"}
        ],
        "scrollY":  "200px",
        "scrollCollapse":   true,
        "paging":     false,
        "order":   [[ 1, "desc" ]],
        "orderable":   true//,
        //"bDestroy":   true
      } );

      $("td.percent").each(function() {
        var r = $(this).text().slice(0,-2)
        var s = r + '% 80%';
        // This will get 's' as 'n% 100%'. We have to only change the width,
        // height remains 100%. We assign this 's' to the css.
        $(this).css({"background-size": s});
        $(this).css({"background-position": "center right"});
      });

      $(".dataTables_scrollFoot td:nth-child(5)").addClass("green_total");
      $(".dataTables_scrollFoot td:nth-child(9)").addClass("yellow_total");
      $(".dataTables_scrollFootInner table").attr("id", "tt-foot");

      $(".dataTables_scrollBody").scroll(function () {  // in top table, scroll header and footer in unison with table body
        $(".dataTables_scrollHead").scrollLeft($(".dataTables_scrollBody").scrollLeft());
        $(".dataTables_scrollFoot").scrollLeft($(".dataTables_scrollBody").scrollLeft());
      });
    }
    else {  // report type = prr_ex
      $('#tt').DataTable( {
        "columns":[
        { "name":"c00", "className": "limit-width"},
        { "name":"c01", "className": "dt-body-right"},
        { "name":"c02", "className": "dt-body-right"},
        { "name":"c03", "className": "dt-body-right"},
        { "name":"c04", "className": "dt-body-right"},
        { "name":"c05", "className": "dt-body-right"},
        { "name":"c06", "className": "dt-body-right"},
        { "name":"c07", "className": "dt-body-right"},
        { "name":"c08", "className": "dt-body-right"},
        { "name":"c09", "className": "dt-body-right"},
        { "name":"c10", "className": "dt-body-right"},
        { "name":"c11", "className": "dt-body-right"}
        ],
        "scrollY":  "200px",
        "scrollCollapse":   true,
        "paging":     false,
        "order":   [[ 1, "desc" ]],
        "orderable":   true//,
        //"bDestroy":   true
      } );

      $("td.percent").each(function() {
        var r = $(this).text().slice(0,-2)
        var s = r + '% 80%';
        // This will get 's' as 'n% 100%'. We have to only change the width,
        // height remains 100%. We assign this 's' to the css.
        $(this).css({"background-size": s});
        $(this).css({"background-position": "center right"});
      });

      $(".dataTables_scrollFoot td:nth-child(4)").addClass("green_total");
      $(".dataTables_scrollFoot td:nth-child(8)").addClass("yellow_total");
      $(".dataTables_scrollFootInner table").attr("id", "tt-foot");

      $("#tt_wrapper .dataTables_scrollHead th:nth-child(5)").css("color","rgb(251,139,137)");  // realign column colors due to now missing row (that displays in serv_perf report)
      $("#tt_wrapper .dataTables_scrollFoot td:nth-child(5)").css("color","rgb(251,139,137)");

      $("#tt_wrapper .dataTables_scrollHead th:nth-child(7)").css("color","rgb(242,200,15)");
      $("#tt_wrapper .dataTables_scrollFoot td:nth-child(7)").css("color","rgb(242,200,15)");

      $("#tt_wrapper .dataTables_scrollHead th:nth-child(9)").css("color","rgb(171,235,255)");
      $("#tt_wrapper .dataTables_scrollFoot td:nth-child(9)").css("color","rgb(171,235,255)");

      $("#tt_wrapper .dataTables_scrollHead th:nth-child(11)").css("color","rgb(242,178,140)");
      $("#tt_wrapper .dataTables_scrollFoot td:nth-child(11)").css("color","rgb(242,178,140)");


      $(".dataTables_scrollBody").scroll(function () {  // in top table, scroll header and footer in unison with table body
        $(".dataTables_scrollHead").scrollLeft($(".dataTables_scrollBody").scrollLeft());
        $(".dataTables_scrollFoot").scrollLeft($(".dataTables_scrollBody").scrollLeft());
      });
    }
  }

  function recreateBottomDataTable() {

    $('#bt').DataTable(
      {
        "columns":[
        { "name":"d0", "className": "limit-width bt00"},
        { "name":"d1", "className": "dt-body-left bt01"},
        { "name":"d2", "className": "dt-body-left bt02"},
        { "name":"d3", "className": "dt-body-left bt03"},
        { "name":"d4", "className": "dt-body-left bt04"},
        { "name":"d5", "className": "dt-body-left bt05"},
        { "name":"d6", "className": "dt-body-left bt06"},
        { "name":"d7", "className": "dt-body-left bt07"},
        { "name":"d8", "className": "dt-body-left bt08"},
        { "name":"d9", "className": "dt-body-left bt09"},
        { "name":"da", "className": "dt-body-left bt10"},
        { "name":"db", "className": "dt-body-left bt11"},
        { "name":"dc", "className": "dt-body-left bt12"},
        { "name":"dd", "className": "dt-body-left bt13"},
        { "name":"de", "className": "dt-body-left bt14"},
        { "name":"df", "className": "dt-body-left bt15"},
        { "name":"dg", "className": "dt-body-left bt16"},
        { "name":"dh", "className": "dt-body-left bt17"},
        { "name":"di", "className": "dt-body-left bt18"},
        { "name":"dj", "className": "dt-body-left bt19"},
        { "name":"dk", "className": "dt-body-left bt20"},
        { "name":"dl", "className": "dt-body-left bt21"}
        ],
        "scrollY":  "200px",
        "scrollCollapse":   true,
        "paging":     true,
        "order":   [[ 1, "desc" ]],
        "orderable":   true,
        "bDestroy":   true
      }
    );
  }

  function buildFooter(pls_footer,sg_footer,bs_footer) {
    //pls_footer

    $.each(picked_sg, function(i, v){
      if ( v == "000" ) { v = "All Servicing Groups"; }
      sg_footer += v+", ";
    });
    sg_footer = sg_footer.slice(0,-2);

    $.each(picked_bs, function(i, v){
      if ( v == "000" ) { w = "All Business Segments"; }
      else if ( v == 1 ) { w = "AMD"; }
      else if ( v == 2 ) { w = "Core Lab"; }
      else if ( v == 4 ) { w = "Transfusion"; }
      else if ( v == 99 ) { w = "Unknown"; }
      else { w = ""; }
      bs_footer += w+", ";
    });
    bs_footer = bs_footer.slice(0,-2);

    $("#detailed_summation>.call_center_footer>ul").
    html("<b>Instruments Selected:</b> "+pls_footer+"<br/><br/><b>Servicing Groups Selected:</b> "+sg_footer+"<br/><br/><b>Business Segments Selected:</b> "+bs_footer);
  }


  // MINIMUM DATE - date normalized so left slider selector can be set
  function normalize_date_min(min_type) {
    // consistent across app, base date interval on format of 'di_min', regardless of what 'di_int' variable claims
    // di_min, if 8 digits without dashes, as in 'day', comes with dashes regardless of whether added in URL or not.
    var norm_min = "", q_min = "", m_min = "", w_min = "";  //di_int = "";

    if ( min_type.length == 4 ) {
      norm_min = min_type+"-01-01";
    } // year 2018

    if ( min_type.toLowerCase().lastIndexOf("q") !== -1 ) {  // quarter 2018Q1
    q_min = min_type.substring(5);
    if ( q_min == 1 ) { m_min = "01"; } else if ( q_min == 2 ) { m_min = "04"; } else if ( q_min == 3 ) { m_min = "07"; } else { m_min = "10"; }
      norm_min = min_type.substring(0,4)+"-"+m_min+"-01";
    }

    if ( min_type.length == 6 && min_type.toLowerCase().lastIndexOf("q") === -1 ) {
      norm_min = min_type.substring(0,4)+"-"+min_type.substring(4)+"-01";
    }  // month  201804

    if ( min_type.toLowerCase().lastIndexOf("w") !== -1 ) {  // week  2018w42
      w_min = min_type.substring(0,4)+min_type.substring(5);
      norm_min = week_dates_start[w_min];
    }

    if ( min_type.toLowerCase().lastIndexOf("-") !== -1 || ( min_type.length == 8 && min_type.toLowerCase().lastIndexOf("-") === -1 ) ) {
      if ( min_type.toLowerCase().lastIndexOf("-") === -1 ) {
        norm_min = min_type;
      }
    }
    return norm_min;
  }

  // MAXIMUM DATE - date normalized so right slider selector can be set
  function normalize_date_max(max_type) {
    var norm_max = "", y_max = "", q_max = "", m_max = "", w_max = "", d_max = "";

    if ( max_type.length == 4 ) {
      norm_max = max_type+"-12-31";
    } // year 2018

    if ( max_type.toLowerCase().lastIndexOf("q") !== -1 ) {  // quarter 2018Q1
    var q_max = max_type.substring(5);
      if ( q_max == 1 ) { q_max = "03"; } else if ( q_max == 2 ) { q_max = "06"; } else if ( q_max == 3 ) { q_max = "09"; } else { q_max = "12"; }
      if ( q_max == "03" || q_max == "12" ) { q_day = "31"; } else { q_day = "30"; }
      norm_max = max_type.substring(0,4)+"-"+q_max+"-"+q_day;
    }

    if ( max_type.length == 6 && max_type.toLowerCase().lastIndexOf("q") === -1 ) {  // month  201804
      y_max = max_type.substring(0,4);
      m_max = max_type.substring(4);
      if ( m_max == "04" || m_max == "06" || m_max == "09" || m_max == "11" ) { d_max = 30; } else if ( m_max == "02" ) { d_max = 28; } else { d_max = 31; }
      if ( ( y_max == "2004" || y_max == "2008" || y_max == "2012" || y_max == "2016" || y_max == "2020" || y_max == "2024" || y_max == "2028" || y_max == "2032" ) && $m_max == "02" ) { d_max = 29; }
      norm_max = y_max+"-"+m_max+"-"+d_max;
    }

    if ( max_type.toLowerCase().lastIndexOf("w") !== -1 ) {  // week  2018w42
      w_max = max_type.substring(0,4)+max_type.substring(5);
      norm_max = week_dates_end[w_max];
    }

    if ( max_type.toLowerCase().lastIndexOf("-") !== -1 || ( max_type.length == 8 && max_type.toLowerCase().lastIndexOf("-") === -1 ) ) {
      if ( max_type.toLowerCase().lastIndexOf("-") === -1 ) {
        norm_max = max_type;
      }
    }  // day
    return norm_max;
  }


  function createInitial() {

    var source = "init";
    once_is_enough = 0;

    var cc = $('#jquery_data #cc').val();

    var minperiod = $('#jquery_data #period-min').val();
    var maxperiod = $('#jquery_data #period-max').val();

    pls_list = $("#jquery_data #pls-nums-f").val();

    // CREATE INITIAL TABLES & CHARTS
    var pl = $('#jquery_data #current-slice-pl').val();

    var checked_pls_list = pls_list.replace(/\'/g,"");
    var checked_pls = checked_pls_list.split(',');

    // TOP TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    buildTopTable(source,checked_pls);
    ttHighlight(cc);

    // Update Meters
    if ( report_type == "serv_perf" ) {
      $('.PRR-meter').html(updateMeter1(cc));
      $('.PPRR-meter').html(updateMeter2(cc));
    }

    // TREND CHART   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    if ( report_type == "serv_perf" ) {

      var cc_full = cc;  // preserves original data item, in case where cc var is subsequently processed
      var checked_pl_groups_all = "";
      if ( cc != "000" && ( sub_type == "ind" || sub_type == "cntr" ) ) { cc = cc.slice(0,cc.indexOf("::")); }
      var period_idx = "";
      buildTrendChart(source,checked_pl_groups_all,notInit,cc,per,period_idx);
    }


    // DONUT CHART   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    if ( report_type == "serv_perf" ) {
      buildDonutChart(source,cc,checked_pls);
    }
    else if ( report_type == "prr_ex" ) {
      stacked_chart_trigger();
    }


    // BOTTOM TABLE   #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
    var org_sql_url = org_data.org_sql.replace(/\', '/g, "','");
    org_sql_url = org_sql_url.replace(/\ /g, '_');

    var newOrg = $('#jquery_data #topmenu-org').val();
    var newInst = "<?php echo $_GET["plgroup"]; ?>";

    var urlstring = "<?php echo $pl_url.$org_url; ?>"+"&org_sql="+org_sql_url+"<?php echo $sg_url.$bs_url; ?>";

    if ( report_type == "serv_perf" ) {
      // footer total selected?
      var found_selected = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr >  td.ui-state-default.limit-width.selected-cc").html();
      // footer total amount
      var this_is_the = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.td-all-totl").html();
    }
    else { // report_type = prr_ex
      var found_selected = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.selected-exp").html();
      var this_is_the = $("#tt_wrapper > .dataTables_scroll > .dataTables_scrollFoot > .dataTables_scrollFootInner > table#tt-foot > tfoot > tr > td.td-all-totl").html();
    }

    if ( typeof found_selected !== 'undefined' && this_is_the >= 10000 ) {  // threshold to display bottom table
      $("#bt_re").html("<div class=\"bottom-table-noselect-text\">please make a selection above</div>");
    }
    else {
      var pl_groups_list = $("#jquery_data #plgs-f").val();
      var pls_list = $("#jquery_data #pls-nums-f").val();

      $('#bt_re').removeClass("blank");

      var bt = $( "#bt" ).DataTable();
      $("#bt_re").empty();
      bt.destroy();

      if ( page_refresh == 1 ) {
        updateBottomTable("","","","","viewer");
        page_refresh = 0;
      }
      else {
        updateBottomTable(pl_groups_list, checked_pls, pls_list, "", "viewer");
      }

      jQuery("#bt_re").ready(checkContainer);
      jQuery("#bt_re").ready(checkContainer1);
      bottomChartCSV();
    }
    top_table_scroll_to();

  }   // end create initial


  function downloadCSV(csv, filename) {
    var csvFile;
    var downloadLink;
    csvFile = new Blob([csv], {type: "text/csv"});
    //for microsoft IE
    if (window.navigator && window.navigator.msSaveOrOpenBlob) {
      window.navigator.msSaveOrOpenBlob(csvFile, filename);
    }
    else { //other browsers
      downloadLink = document.createElement("a");
      downloadLink.download = filename;
      downloadLink.href = window.URL.createObjectURL(csvFile);
      downloadLink.style.display = "none";
      document.body.appendChild(downloadLink);
      downloadLink.click();
    }
  }

  function exportTopTableToCSV(filename) {
    var csv = [];
    var header_rows = document.querySelectorAll("#tt_wrapper table.dataTable thead tr");

    // Capture header row
    var row = [], cols = header_rows[0].querySelectorAll("th .DataTables_sort_wrapper");

    if ( is_IE == 0 ) {
      for (var j = 0; j < cols.length; j++)
      row.push(cols[j].innerText.replace("\n"," "));
    }
    else { // special IE method
      for (var j = 0; j < cols.length; j++)
      row.push(cols[j].innerText.replace("\r\n"," "));
    }

    csv.push(row.join(","));

    // Body rows
    var rows = document.querySelectorAll("table#tt tbody tr");

    for (var i = 0; i < rows.length; i++) {
      var row = [], cols = rows[i].querySelectorAll("td, th");

      var j = 0; var k = cols.length;
      $.each(cols, function() {
        var col = cols[j].innerText;
        if ( col.indexOf(',') !== -1 ){  // contains comma, for csv to properly process the column, needs extra quotes
          col = '"'+col+'"';
        }
        row.push(col);
        j++;
      });

      csv.push(row.join(","));
    }

    // Download CSV file
    downloadCSV(csv.join("\n"), filename);
  }

  function exportBottomTableToCSV(filename) {
    var csv = [];
    var header_rows = document.querySelectorAll("#bt-csv thead tr");

    // Capture header row
    var row = [], cols = header_rows[0].querySelectorAll("th");

    for (var j = 0; j < cols.length; j++)
    row.push(cols[j].innerText.replace("\r\n"," "));

    csv.push(row.join(","));

    // Body rows
    var rows = document.querySelectorAll("#bt-csv tbody tr");

    for (var i = 0; i < rows.length; i++) {
      var row = [], cols = rows[i].querySelectorAll("td, th");

      var j = 0; var k = cols.length;
      $.each(cols, function() {
        var col = cols[j].innerText;
        if ( col.indexOf(',') !== -1 ){  // contains comma, for csv to properly process the column, needs extra quotes
          col = '"'+col+'"';
        }
        row.push(col);
        j++;
      });

      csv.push(row.join(","));
    }

    // Download CSV file
    downloadCSV(csv.join("\n"), filename);
  }

  function checkContainer () {
    var timer_cutout = 0;
    if($('#bt').is(':visible')){ $('#bt').html(recreateBottomDataTable); }
    else { if ( timer_cutout < 30 ) { setTimeout(checkContainer, 1000);
    timer_cutout++; } }
  }

  function checkContainer1 () {
    var timer_cutout1 = 0;
    if($('#bt_wrapper').is(':visible')){
      if ( is_IE == 0 ) { $('#bt_re .bottom-table-throbber').css("display","none"); }
      $('#bt_wrapper .dataTables_scroll .dataTables_scrollBody table').css("opacity","1");
      $('#bt_wrapper .dataTables_scroll .dataTables_scrollHead .dataTables_scrollHeadInner .dataTable').css("opacity","1");
    }
    else { if ( timer_cutout1 < 30 ) { setTimeout(checkContainer1, 1000);
      timer_cutout1++; } }
  }



  $(document).ready(function() {

    createInitial(); // generates entire initial iframe contents, also regenerates iframe when "return to all periods" button is pressed on trend chart

    $("#tt_re").on("mouseout mouseleave",function() {
      if ( once_is_enough == 0 ) {
        top_table_scroll_to();
        once_is_enough = 1;
      }
    });

    var plgroup = "<?php echo $_REQUEST['plgroup']; ?>";
    // xx-out -- need to check both vars, as in some cases top instruments selector overrides donut chart instrument selection
    //var donut_plg = "<?php echo $_REQUEST['donut_plg']; ?>";

    $(".pgsel-checkbox input").removeAttr("checked");
    if ( plgroup == "000" ) {  // check relevant donut chart checkboxes
      $(".pgsel-checkbox input").attr("checked","checked");
      $(".pgsel-checkbox input").prop("checked",true);
      $(".pgsel_check-all").removeAttr("checked");
      $(".pgsel_check-none").removeAttr("checked");
      $(".pgsel-checkbox input").removeAttr("disabled");
    }
    else {
      $(".pgsel-checkbox input[type='checkbox']").filter(function(){ return this.value === plgroup; }).attr("checked","checked").prop("checked",true);
    }

    if ( plgroup != "000" ) {  // need to disable Product Group checkboxes while Top Menu Instruments selector prevails.
      $(".pgsel-checkbox input").attr("disabled", true);
      $(".donut-right").prepend('<div class="donut-right-prepend-warning">To select within this chart, reset Top Menu Instruments to \'All Instruments\'</div>');
      $(".donut-right").prepend('<div class="donut-right-disable-shield"></div>');
    }

    // top chart CSV
    $("#top_chart_csv_icon").click(function() {
      exportTopTableToCSV('call-center_top-table_summary.csv')
    });
    bottomChartCSV();
  });

  // bottom chart CSV
  function bottomChartCSV() {
    var check_if = $("#bt_re .bottom-table-noselect-text").text();

    if ( check_if.length == 0 ) {
      var timer_cutout2 = 0;
      jQuery("#bt_re").ready(checkContainer2);  // need to make sure container is there before click can be instituted
      function checkContainer2 () {
        if($('#bt_wrapper').is(':visible')) {
          $("#bottom_chart_csv_icon").click(function() {
            exportBottomTableToCSV('call-center_bottom-table_tickets.csv')
          });
        }
        else { if ( timer_cutout2 < 30 ) { setTimeout(checkContainer2, 1000);
        timer_cutout2++; } }
      }
    }
  }

  $(function(){
    $('.call_center_footer_button').click(function(){
      $('.call_center_footer').slideToggle('fast');
    });
    $('.call_center_footer').hide();
  });

  function top_table_scroll_to() {  // centers selected country in top table, regardless of other selections made in other charts in iframe
    var found_selected = $(".dataTables_scrollBody > table#tt > tbody > tr > td.td-ctry.limit-width.selected-cc").html();
    if ( typeof found_selected !== 'undefined' ) { $(".dataTables_scrollBody").scrollTop($('tr.selected-row').offset().top - 200); }
  }


</script>

<?php
echo GSR_STOP_PROGRESS_BAR.'<br><br>';

?>
</body>