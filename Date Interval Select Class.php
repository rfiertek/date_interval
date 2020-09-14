<?php
/**
* date_interval_select_class.php
*
* $Revision: 1220 $
*
* $Date: 2019-03-04 12:50:24 -0600 (Mon, 04 Mar 2019) $
*
* $Id: date_interval_select_class.php 1220 2019-03-04 18:50:24Z fiertekr $
*
* @package date_interval_select_class
* @author Robert Fiertek
* @version $Revision: 1220 $  $Id: date_interval_select_class.php 1220 2019-03-04 18:50:24Z fiertekr $
*
*
*/

// Required classes
require_once( $_SERVER["DOCUMENT_ROOT"] . '/gsr/common/class/gsr_gui_class.php');

/**
* date_interval_select_class - Class for building date interval select html code
*
*/
class date_interval_select_class {

  var $params;

  public $jq1;

  /**
  * Generates Date Interval Selection HTML
  *
  * <b>Parameters array structure:</b><br>
  * - 'db_obj': Database connection object
  * - 'special_sql_filename': PHP file that contains the special date interval sql (SQL must be contained in variable 'sql') (Optional)
  * - 'request': The $_REQUEST to allow for preselection of items. (default - none)
  *
  * @param array $params
  * @return object
  */
  function __construct($params = array()) {
    $this->params = $params;

    $di_min_date = date("Ym", strtotime( date( "Ym", strtotime( date("Ym") ) ) . "-13 months" ) );
    $di_max_date = date("Ym", strtotime( date( "Ym", strtotime( date("Ym") ) ) . "-0 month" ) );
    $di_first_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . "-13 months" ) );
    $di_last_date = new DateTime("now"); $di_last_date = $di_last_date->format("Y-m-d");

    $preserve_int = 0;

    // check if parameters is valid array
    if ( is_array($this->params) ) {
      $valid_params_array = true;
      if ( !array_key_exists('db_obj',$this->params) ) $valid_params_array = false; // default value
      if ( !array_key_exists('special_sql_filename',$this->params) ) $this->params['special_sql_filename'] = ''; // default value

      if ( array_key_exists('request',$this->params) ) {

        // DI_MIN
        if ( !array_key_exists('di_min',$this->params['request']) ) {
          // no di_min
          $di_min_exists = 0;
          $this->params['request']['di_min'] = $di_min_date;
        }
        else {
          $di_min_exists = 1;
        }
        // DI_INT
        if ( !array_key_exists('di_int',$this->params['request']) ) {  // di_int does not exist, thus make it default 'month'
          $this->params['request']['di_int'] = 'm';
        }
        else {  // as di_int DOES EXIST, IF di_min is missing, di_int will provide di_min and di_max format, IF di_min exists, it will determine di_int variable
          if ( $di_min_exists == 0 ) { // di_int drives di_min and di_max format
            $preserve_int = 1;
          }
        }
        if ( !array_key_exists('di_max',$this->params['request']) ) {
          $this->params['request']['di_max'] = $di_max_date;
        }
        if ( !array_key_exists('di_display',$this->params['request']) ) {
          $this->params['request']['di_display'] = '000';
        }
        if ( !array_key_exists('di_first',$this->params['request']) ) {
          $this->params['request']['di_first'] = $di_first_date;
        }
        if ( !array_key_exists('di_last',$this->params['request']) ) {
          $this->params['request']['di_last'] = $di_last_date;
        }
        $this->params['request']['preserve_int'] = $preserve_int;  // save derived field $preserve_int
      }
      else {  // defaults to 'month' level
        $this->params['request'] = array('di_min'=>$di_min_date,'di_max'=>$di_max_date,'di_int'=>'m','di_display'=>'000','di_first'=>$di_first_date,'di_last'=>$di_last_date,'preserve_int'=>$preserve_int);
      }
    }

    //echo $di_min_date." 4:";

    if(!$valid_params_array){
      echo '<span style="color: red">ERROR: db_obj value is required.</span>';
      exit;
    }
    $this->db_obj = $this->params['db_obj'];
    $this->gui_obj = new gsr_gui_class();

    // Check for special sql filename and load if exist
    $this->default_sql_used = false;
    $sql='';

    if($this->params['special_sql_filename'] <> ''){
      // include the file, which set the $sql variable
      if (is_file($this->params['special_sql_filename'])) {
        require($this->params['special_sql_filename']);
      } else {
        echo '<span style="color: red">ERROR: The special sql file:</span>
        <span style="color: blue"><b>'. $this->params['special_sql_filename'] .'
        </b></span><span style="color: red"> not found.</span>';
        exit;
      }
    }
  }

  /**
  * Generates Date Interval Selection HTML
  *
  * @return string generated HTML
  */
  public function gen_date_interval_select_html() {

  $ret = array();

  $slider_values = "";

  $w_vals = array();  // d_vals and d norm vals derived below
  $m_vals = array();
  $q_vals = array();
  $y_vals = array();

  $w_norm_vals_min = array();
  $m_norm_vals_min = array();
  $q_norm_vals_min = array();
  $y_norm_vals_min = array();

  $w_norm_vals_max = array();
  $m_norm_vals_max = array();
  $q_norm_vals_max = array();
  $y_norm_vals_max = array();

  $weeks = array(); // creating these arrays, and json encoding them to use in jquery, as 'week' is a built in PHP function, thus dependable and consistent (jquery has no such function)
  $week_dates_start = array();
  $week_dates_end = array();


  // When 'di_min' is specifically called, either in the URL or in a page redraw, the type of 'min' formatting (2018,2018q1,201808,2018w04,2018-04-01) overrides the interval variable.
  // this variable prevents override of the 'interval' field, given no/default 'min' info (when 'min' is set above due to no 'min' info provided otherwise)
  $preserve_int = $this->params['request']['preserve_int'];

  // CLEAN UP request string - remove extraneous characters, verify valid entry, post alert message if not valid
  $err_min = $err_max = $err_first = $err_last = $err_int = $err_display = 0;
  if ( isset($this->params['request']['di_min']) ) {
    $this->params['request']['di_min'] = preg_replace("/[^a-zA-Z0-9\-]+/", "", $this->params['request']['di_min']);
    $slug = $this->params['request']['di_min'];
    if (
      strlen($slug) >= 4 && strlen($slug) <= 10 && strlen($slug) != 5 && strlen($slug) != 9 && substr($slug,0,4) <= date("Y")+1 &&
      ( ( strlen($slug) == 4 && preg_match("/^[0-9]+$/", $slug)  ) ||
      ( strlen($slug) == 6 && preg_match("/^[0-9]+$/", $slug) ) ||
      ( strlen($slug) == 6 && stripos($slug, 'q') == 4 && preg_match("/^[0-9]+$/", substr($slug,0,4)) && preg_match("/^[0-9]+$/", substr($slug,5,1) )
      && substr($slug,5,1) <= 4 && substr($slug,5,1) >= 1 ) ||
      ( strlen($slug) == 7 && stripos($slug, 'w') == 4 && preg_match("/^[0-9]+$/", substr($slug,0,4)) && preg_match("/^[0-9]+$/", substr($slug,5,2) )
      && substr($slug,5,2) <= 53 && substr($slug,5,2) >= 1 ) ||
      ( strlen($slug) == 8 && preg_match("/^[0-9]+$/", $slug) && substr($slug,4,2) <= 12 && substr($slug,4,2) >= 1 && substr($slug,6,2) <= 31 && substr($slug,6,2) >= 1 ) ||
      ( strlen($slug) == 10 && stripos($slug, '-') == 4 && strripos($slug, '-') == 7 && preg_match("/^[0-9]+$/", substr($slug,0,4))
      && preg_match("/^[0-9]+$/", substr($slug,5,2) ) && preg_match("/^[0-9]+$/", substr($slug,8,2) ) && substr($slug,5,2) <= 12 && substr($slug,5,2) >= 1
      && substr($slug,8,2) <= 31 && substr($slug,8,2) >= 1 )
      )
    ) {} else { $err_min = 1;  $this->params['request']['di_min'] = date("Ym", strtotime( date( "Ym", strtotime( date("Ym") ) ) . "-12 months" ) ); }
  }
  if ( isset($this->params['request']['di_max']) ) {
    $this->params['request']['di_max'] = preg_replace("/[^a-zA-Z0-9\-]+/", "", $this->params['request']['di_max']);
    $slug = $this->params['request']['di_max'];
    if (
      strlen($slug) >= 4 && strlen($slug) <= 10 && strlen($slug) != 5 && strlen($slug) != 9 && substr($slug,0,4) <= date("Y")+1 &&
      ( ( strlen($slug) == 4 && preg_match("/^[0-9]+$/", $slug)  ) ||
      ( strlen($slug) == 6 && preg_match("/^[0-9]+$/", $slug) ) ||
      ( strlen($slug) == 6 && stripos($slug, 'q') == 4 && preg_match("/^[0-9]+$/", substr($slug,0,4)) && preg_match("/^[0-9]+$/", substr($slug,5,1) )
      && substr($slug,5,1) <= 4 && substr($slug,5,1) >= 1 ) ||
      ( strlen($slug) == 7 && stripos($slug, 'w') == 4 && preg_match("/^[0-9]+$/", substr($slug,0,4)) && preg_match("/^[0-9]+$/", substr($slug,5,2) )
      && substr($slug,5,2) <= 53 && substr($slug,5,2) >= 1 ) ||
      ( strlen($slug) == 8 && preg_match("/^[0-9]+$/", $slug) && substr($slug,4,2) <= 12 && substr($slug,4,2) >= 1 && substr($slug,6,2) <= 31 && substr($slug,6,2) >= 1 ) ||
      ( strlen($slug) == 10 && stripos($slug, '-') == 4 && strripos($slug, '-') == 7 && preg_match("/^[0-9]+$/", substr($slug,0,4))
      && preg_match("/^[0-9]+$/", substr($slug,5,2) ) && preg_match("/^[0-9]+$/", substr($slug,8,2) ) && substr($slug,5,2) <= 12 && substr($slug,5,2) >= 1
      && substr($slug,8,2) <= 31 && substr($slug,8,2) >= 1 )
      )
    ) {} else { $err_max = 1; $this->params['request']['di_max'] = date("Ym", strtotime( date( "Ym", strtotime( date("Ym") ) ) . "-1 month" ) ); }
  }
  if ( isset($this->params['request']['di_first']) ) {
    $this->params['request']['di_first'] = preg_replace("/[^0-9\-]+/", "", $this->params['request']['di_first']);
    $slug = $this->params['request']['di_first'];
    if ( strlen($slug) == 10 && stripos($slug, '-') == 4 && strripos($slug, '-') == 7 && preg_match("/^[0-9]+$/", substr($slug,0,4))
    && preg_match("/^[0-9]+$/", substr($slug,5,2) ) && preg_match("/^[0-9]+$/", substr($slug,8,2) )  && substr($slug,5,2) <= 12
    && substr($slug,5,2) >= 1 && substr($slug,8,2) <= 31 && substr($slug,8,2) >= 1 ) {}
    else { $err_first = 1; $this->params['request']['di_first'] = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . "-13 months" ) ); }
  }
  if ( isset($this->params['request']['di_last']) ) {
    $this->params['request']['di_last'] = preg_replace("/[^0-9\-]+/", "", $this->params['request']['di_last']);
    $slug = $this->params['request']['di_last'];
    if (   strlen($slug) == 10 && stripos($slug, '-') == 4 && strripos($slug, '-') == 7 && preg_match("/^[0-9]+$/", substr($slug,0,4))
    && preg_match("/^[0-9]+$/", substr($slug,5,2) ) && preg_match("/^[0-9]+$/", substr($slug,8,2) )  && substr($slug,5,2) <= 12
    && substr($slug,5,2) >= 1 && substr($slug,8,2) <= 31 && substr($slug,8,2) >= 1 ) {}
    else { $err_last = 1; new DateTime("now"); $this->params['request']['di_last'] = $di_last_date->format("Y-m-d"); }
  }
  if ( isset($this->params['request']['di_int']) ) {
    $this->params['request']['di_int'] = preg_replace("/[^yqmwdYQMWD]+/", "", $this->params['request']['di_int']);
    $slug = $this->params['request']['di_int'];
    if ( strlen($slug) == 1 ) {}
    else { $err_int = 1; }
  }
  if ( isset($this->params['request']['di_display']) ) {
    $this->params['request']['di_display'] = preg_replace("/[^yqmwdYQMWD0]+/", "", $this->params['request']['di_display']);
    $slug = $this->params['request']['di_display'];
    if (
    strlen($slug) >= 1 && strlen($slug) <= 5
    ) {} else { $err_display = 1; }
  }

  ?>
  <script>
  err_min = <?php echo $err_min; ?>;
  err_max = <?php echo $err_max; ?>;
  err_first = <?php echo $err_first; ?>;
  err_last = <?php echo $err_last; ?>;
  err_int = <?php echo $err_int; ?>;
  err_display = <?php echo $err_display; ?>;

  var errs = '<div class="dialog-title-wrapper"><div class="dialog-logo">!</div><div class="dialog-title">Note</div><div class="dialog-close"><img src="/gsr/common/images/icons/close_small.gif" alt="Close" id="date_interval_dialog_close" class="gbox-close-button"></div></div><div class="dialog-body"><div class="dialog-subtitle">To ensure proper interpretation by the Dates & Intervals selector, in your URL query string, please submit query variables in the following manner:</div>';

  if ( err_min == 1 || err_max == 1 ) { errs += '<div class="dialog-category-subtitle">Please submit \'di_min=\' and \'di_max=\' in one of the following formats:</div><div class="dialog-category-guts"><ul><li><b>Year:</b> <i>yyyy</i> [example: <span class="dialog-example">di_min=2018</span>]</li><li><b>Quarter:</b> <i>yyyy</i><span class="dialog-hilite">Q</span><i>q</i>, with quarter being between 1 and 4 [example: <span class="dialog-example">di_min=2018q1</span> -- either \'Q\' or \'q\' is fine]</li><li><b>Month:</b> <i>yyyymm</i> [example: <span class="dialog-example">di_min=201808</span>]</li><li><b>Week:</b> <i>yyyy</i><span class="dialog-hilite">W</span><i>ww</i> - making sure week number has two digits. [example: <span class="dialog-example">di_min=2018w04</span> or <span class="dialog-example">di_min=2018W23</span> -- either \'W\' or \'w\' is fine]</li><li><b>Day:</b> <i>yyyy</i><b>-</b><i>mm</i><b>-</b><i>dd</i>, including dashes \'-\'. [example: <span class="dialog-example">di_min=2017-04-03</span>]</li></ul>Also, check your numbers -- year: this year or prior, month: 01 - 12, quarter: 1 - 4, week: 01 - 53 (in years with a 53rd week), day: 01 - 31 (depending on month).</div>'; }

  if ( err_first == 1 || err_last == 1 ) { errs += '<div class="dialog-category-subtitle">Please submit \'di_first=\' and \'di_last=\' in the following format:</div><div class="dialog-category-guts">As <i>yyyy</i><span class="dialog-hilite">-</span><i>mm</i><span class="dialog-hilite">-</span><i>dd</i>, including dashes \'-\'. [example: <span class="dialog-example">di_first=2017-04-03</span>]<br/>Also, make sure the numbers are logical -- year: this year or prior, month: 01 - 12, day: 01 - 31 (depending on month).</div>'; }

  if ( err_int == 1 ) { errs += '<div class="dialog-category-subtitle">Please submit \'di_int=\' in the following format:</div><div class="dialog-category-guts">As one of the following single characters (y, q, m, w, d), case does not matter. [example: <span class="dialog-example">di_int=w</span>]</div>'; }

  if ( err_display == 1 ) { errs += '<div class="dialog-category-subtitle">Please submit \'di_display=\' in the following format:</div><div class="dialog-category-guts">As any combination of the following characters (y, q, m, w, d), case does not matter, or as \'000\', which means all buttons display. <br/>[example: <span class="dialog-example">di_display=wyq</span> or <span class="dialog-example">di_display=000</span>]</div>'; }

  errs += "</div>";
  if ( err_min == 1 || err_max == 1 || err_first == 1 || err_last == 1 || err_int == 1 || err_display == 1 ) { var showerr = 1; }

  </script>
  <?php

  $di_min_date = date("Ym", strtotime( date( "Ym", strtotime( date("Ym") ) ) . "-12 months" ) );
  $di_max_date = date("Ym", strtotime( date( "Ym", strtotime( date("Ym") ) ) . "-1 month" ) );
  $di_min_norm_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . "-12 months" ) );
  $di_max_norm_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . "-1 month" ) );

  if ( isset($this->params['request']['di_min']) ) { $di_min = $this->params['request']['di_min']; } else { $di_min = $di_min_date; }
  if ( isset($this->params['request']['di_max']) ) { $di_max = $this->params['request']['di_max']; } else { $di_max = $di_max_date; }
  if ( isset($this->params['request']['di_int']) ) { $di_int = $this->params['request']['di_int']; } else { $di_int = 'm'; }

  // NORMALIZE TO Y-M-D
  // normalize min and max dates, to make them consistently year-month-day -- for use in slider, which works at the day level, translating dates into other intervals on the fly
  // this tries to make sense of min and max input data, but it does not account for all cases of end user tampering with incoming data formatting,
  // primarily only translating min and max data initially preset/set by this widget

  // try to figure out how di_min is structured, and create a normalized di min (y-m-d) or else revert to default di min if this variable will not parse into one of the five interval types.
  $norm_min = $norm_max = 0;
  // MINIMUM DATE  // result is to have dashes in format y-m-d
  if ( strlen($di_min) == 4 ) {
    $norm_min = $di_min."-01-01";
    $di_min_format = "y";
  } // year 2018
  elseif ( stripos($di_min, 'q') !== false ) {  // quarter 2018Q1
    $q_min = substr($di_min,5,1);
    if ( $q_min == 1 ) { $m_min = "01"; } elseif ( $q_min == 2 ) { $m_min = "04"; } elseif ( $q_min == 3 ) { $m_min = "07"; } else { $m_min = "10"; }
    $norm_min = substr($di_min,0,4)."-".$m_min."-01";
    $di_min_format = "q";
  }
  elseif ( strlen($di_min) == 6 && stripos($di_min, 'q') === false ) {
    $norm_min = substr($di_min,0,4)."-".substr($di_min,4,2)."-01";
    $di_min_format = "m";
  }  // month  201804
  elseif ( stripos($di_min, 'w') !== false ) {  // week  2018w42
    $gendate = new DateTime();
    $gendate->setISODate(substr($di_min,0,4),substr($di_min,5,2),1);
    $norm_min = $gendate->format('Y-m-d');
    $di_min_format = "w";
  }
  elseif ( stripos($di_min, '-') !== false || ( strlen($di_min) == 8 && strpos($di_min, "-") === false ) ) {
    if ( strpos($di_min, "-") === false ) {
      $norm_min = substr($di_min,0,4)."-".substr($di_min,4,2)."-".substr($di_min,6,2);  // 2018-09-15
    }
    else {
      $norm_min = $di_min;
    }
    $di_min_format = "d";
  }  // day
  else {
    $norm_min = $di_min_date;
    $di_min_format = "m";  // defaults
  }

  // after creation of normalized min (y-m-d format), translate the normalized date into all interval types and save these interval types into jquery data fields.
  function min_types($input_min) {
    if ( strlen($input_min) == 4 ) { $format = "y"; }
    elseif ( stripos($input_min, 'q') !== false ) { $format = "q"; }
    elseif ( strlen($input_min) == 6 && stripos($input_min, 'q') === false ) { $format = "m"; }
    elseif ( stripos($input_min, 'w') !== false ) { $format = "w"; }
    elseif ( stripos($input_min, '-') !== false || ( strlen($input_min) == 8 && strpos($input_min, "-") === false ) ) { $format = "d"; }
    else {}

    if ( $format == "y" ) {
      $date = new DateTime($input_min."-01-01");
      $w_yr = $date->format("o");
      $w_wk = $date->format("W");
      if ( strlen($w_wk) == 1 ) { $w_wk = "0".$w_wk; }

      $min_type['y'] = $input_min;
      $min_type['q'] = $input_min."Q1";
      $min_type['m'] = $input_min."01";
      $min_type['w'] = $w_yr."W".$w_wk;
      $min_type['d'] = $input_min."-01-01";
    }
    elseif ( $format == "q" ) {
      $yr = substr($input_min,0,4);

      $qu = substr($input_min,5,1);
      if ( $qu == "1" ) { $mq = "01"; } elseif ( $qu == "2" ) { $mq = "04"; } elseif ( $qu == "3" ) { $mq = "07"; } else { $mq = "10"; }

      $date = new DateTime($yr."-".$mq."-01");
      $w_yr = $date->format("o");
      $w_wk = $date->format("W");
      if ( strlen($w_wk) == 1 ) { $w_wk = "0".$w_wk; }

      $min_type['y'] = $yr;
      $min_type['q'] = $input_min;
      $min_type['m'] = $yr.$mq;
      $min_type['w'] = $w_yr."W".$w_wk;
      $min_type['d'] = $yr."-".$mq."-01";
    }
    elseif ( $format == "m" ) {
      $yr = substr($input_min,0,4);
      $mo = substr($input_min,4,2);  // 201808

      if ( $mo == "01" || $mo == "02" || $mo == "03" ) { $qu = "1"; } elseif ( $mo == "04" || $mo == "05" || $mo == "06" ) { $qu = "2"; } elseif ( $mo == "07" || $mo == "08" || $mo == "09" ) { $qu = "3"; }
      else { $qu = "4"; }

      $date = new DateTime($yr."-".$mo."-01");
      $w_yr = $date->format("o");
      $w_wk = $date->format("W");
      if ( strlen($w_wk) == 1 ) { $w_wk = "0".$w_wk; }

      $min_type['y'] = $yr;
      $min_type['q'] = $yr."Q".$qu;
      $min_type['m'] = $input_min;
      $min_type['w'] = $w_yr."W".$w_wk;
      $min_type['d'] = $yr."-".$mo."-01";
    }
    elseif ( $format == "w" ) {
      $yr = substr($input_min,0,4);
      $wk = substr($input_min,5,2);  //2018w03

      $date = new DateTime();
      $date->setISODate($yr, $wk);
      $wk_st_day = $date->format('Y-m-d');
      $wk_st_mo = $date->format('Ym');
      $mo = $date->format('m');

      if ( $mo == "01" || $mo == "02" || $mo == "03" ) { $qu = "1"; } elseif ( $mo == "04" || $mo == "05" || $mo == "06" ) { $qu = "2"; } elseif ( $mo == "07" || $mo == "08" || $mo == "09" ) { $qu = "3"; }
      else { $qu = "4"; }

      $min_type['y'] = $yr;
      $min_type['q'] = $yr."Q".$qu;
      $min_type['m'] = $wk_st_mo;
      $min_type['w'] = $input_min;
      $min_type['d'] = $wk_st_day;
    }
    elseif ( $format == "d" ) {
      $yr = substr($input_min,0,4);
      $mo = substr($input_min,5,2);

      $date = new DateTime($input_min);
      $w_yr = $date->format("o");
      $w_wk = $date->format("W");
      if ( strlen($w_wk) == 1 ) { $w_wk = "0".$w_wk; }

      if ( $mo == "01" || $mo == "02" || $mo == "03" ) { $qu = "1"; } elseif ( $mo == "04" || $mo == "05" || $mo == "06" ) { $qu = "2"; } elseif ( $mo == "07" || $mo == "08" || $mo == "09" ) { $qu = "3"; }
      else { $qu = "4"; }

      $min_type['y'] = $yr;
      $min_type['q'] = $yr."Q".$qu;
      $min_type['m'] = $yr.$mo;
      $min_type['w'] = $w_yr."W".$w_wk;
      $min_type['d'] = $input_min;
    }

    return $min_type;
  }

  function max_types($input_max) {
    if ( strlen($input_max) == 4 ) { $format = "y"; }
    elseif ( stripos($input_max, 'q') !== false ) { $format = "q"; }
    elseif ( strlen($input_max) == 6 && stripos($input_max, 'q') === false ) { $format = "m"; }
    elseif ( stripos($input_max, 'w') !== false ) { $format = "w"; }
    elseif ( stripos($input_max, '-') !== false || ( strlen($input_max) == 8 && strpos($input_max, "-") === false ) ) { $format = "d"; }
    else {}

    if ( $format == "y" ) {
      $date = new DateTime($input_max."-12-31");
      $w_yr = $date->format("o");
      $w_wk = $date->format("W");
      if ( strlen($w_wk) == 1 ) { $w_wk = "0".$w_wk; }

      $max_type['y'] = $input_max;
      $max_type['q'] = $input_max."Q4";
      $max_type['m'] = $input_max."12";
      $max_type['w'] = $w_yr."W".$w_wk;
      $max_type['d'] = $input_max."-12-31";
    }
    elseif ( $format == "q" ) {
      $yr = substr($input_max,0,4);

      $qu = substr($input_max,5,1);
      if ( $qu == "1" ) { $mq = "03"; } elseif ( $qu == "2" ) { $mq = "06"; } elseif ( $qu == "3" ) { $mq = "09"; } else { $mq = "12"; }
      if ( $mq == "03" || $mq == "12" ) { $dq = "31"; } else { $dq = "30"; }

      $date = new DateTime($yr."-".$mq."-".$dq);
      $w_yr = $date->format("o");
      $w_wk = $date->format("W");
      if ( strlen($w_wk) == 1 ) { $w_wk = "0".$w_wk; }

      $max_type['y'] = $yr;
      $max_type['q'] = $input_max;
      $max_type['m'] = $yr.$mq;
      $max_type['w'] = $w_yr."W".$w_wk;
      $max_type['d'] = $yr."-".$mq."-".$dq;
    }
    elseif ( $format == "m" ) {
      $yr = substr($input_max,0,4);
      $mo = substr($input_max,4,2);  // 201808

      if ( $mo == "01" || $mo == "02" || $mo == "03" ) { $qu = "1"; } elseif ( $mo == "04" || $mo == "05" || $mo == "06" ) { $qu = "2"; } elseif ( $mo == "07" || $mo == "08" || $mo == "09" ) { $qu = "3"; }
      else { $qu = "4"; }
      if ( $mo == "04" || $mo == "06" || $mo == "09" || $mo == "11" ) { $dq = "30"; } else { $dq = "31"; }
      $yr1 = $yr % 4; if ( $mo == "02" ) { if ( $yr1 == 0 ) { $dq = 29; } else { $dq = 28; } }

      $date = new DateTime($yr."-".$mo."-".$dq);
      $w_yr = $date->format("o");
      $w_wk = $date->format("W");
      if ( strlen($w_wk) == 1 ) { $w_wk = "0".$w_wk; }

      $max_type['y'] = $yr;
      $max_type['q'] = $yr."Q".$qu;
      $max_type['m'] = $input_max;
      $max_type['w'] = $w_yr."W".$w_wk;
      $max_type['d'] = $yr."-".$mo."-".$dq;
    }
    elseif ( $format == "w" ) {
      $yr = substr($input_max,0,4);
      $wk = substr($input_max,5,2);  //2018w03

      $date = new DateTime();
      $date->setISODate($yr, $wk);
      //$wk_st_day = $date->format('Y-m-d');
      $wk_end_day = $date->modify('+6 days')->format('Y-m-d');
      $wk_end_mo = $date->format('Ym');
      $mo = $date->format('m');

      if ( $mo == "01" || $mo == "02" || $mo == "03" ) { $qu = "1"; } elseif ( $mo == "04" || $mo == "05" || $mo == "06" ) { $qu = "2"; } elseif ( $mo == "07" || $mo == "08" || $mo == "09" ) { $qu = "3"; }
      else { $qu = "4"; }

      $max_type['y'] = $yr;
      $max_type['q'] = $yr."Q".$qu;
      $max_type['m'] = $wk_end_mo;
      $max_type['w'] = $input_max;
      $max_type['d'] = $wk_end_day;
    }
    elseif ( $format == "d" ) {
      $yr = substr($input_max,0,4);
      $mo = substr($input_max,5,2);

      $date = new DateTime($input_max);
      $w_yr = $date->format("o");
      $w_wk = $date->format("W");
      if ( strlen($w_wk) == 1 ) { $w_wk = "0".$w_wk; }

      if ( $mo == "01" || $mo == "02" || $mo == "03" ) { $qu = "1"; } elseif ( $mo == "04" || $mo == "05" || $mo == "06" ) { $qu = "2"; } elseif ( $mo == "07" || $mo == "08" || $mo == "09" ) { $qu = "3"; }
      else { $qu = "4"; }

      $max_type['y'] = $yr;
      $max_type['q'] = $yr."Q".$qu;
      $max_type['m'] = $yr.$mo;
      $max_type['w'] = $w_yr."W".$w_wk;
      $max_type['d'] = $input_max;
    }

    return $max_type;
  }

  $min_type = min_types($norm_min);

  // if di_int is not prevailing ($preserve_int = 0), then derive di_int from di_min format
  if ( $preserve_int == 1 ) {
    //change di min to int format
    if ( $di_int == "y" ) { $min = $min_type['y']; }
    if ( $di_int == "q" ) { $min = $min_type['q']; }
    if ( $di_int == "m" ) { $min = $min_type['m']; }
    if ( $di_int == "w" ) { $min = $min_type['w']; }
    if ( $di_int == "d" ) { $min = $min_type['d']; }
  }
  else {
    $di_int = $di_min_format;
    $min = $min_type[$di_int];
  }

  $di_min_type = min_types($min);
  $di_min = $di_min_type[$di_int];

  // once di_min interval type is determined, generate di_max normalized date, save all date interval types too, then create 'max' using the same interval type as 'min'

  // MAXIMUM DATE  // result is to have dashes
  if ( strlen($di_max) == 4 ) {
    $norm_max = $di_max."-12-31";
  } // year 2018
  elseif ( stripos($di_max, 'q') !== false ) {  // quarter 2018Q1
    $q_max = substr($di_max,5,1);
    if ( $q_max == 1 ) { $q_max = "03"; } elseif ( $q_max == 2 ) { $q_max = "06"; } elseif ( $q_max == 3 ) { $q_max = "09"; } else { $q_max = "12"; }
    if ( $q_max == "03" || $q_max == "12" ) { $q_day = "31"; } else { $q_day = "30"; }
    $norm_max = substr($di_max,0,4)."-".$q_max."-".$q_day;
  }
  elseif ( strlen($di_max) == 6 && stripos($di_max, 'q') === false ) {  // month  201804
    $y_max = substr($di_max,0,4);
    $m_max = substr($di_max,4,2);
    if ( $m_max == "04" || $m_max == "06" || $m_max == "09" || $m_max == "11" ) { $d_max = 30; } elseif ( $m_max == "02" ) { $d_max = 28; } else { $d_max = 31; }
    $time = strtotime($m_max.'/01/'.$y_max);
    $thisy = date('Y',$time); $thisy = $thisy % 4;
    if ( $thisy == 0 && $m_max == "02" ) { $d_max = 29; }
    $norm_max = $y_max."-".$m_max."-".$d_max;
  }
  elseif ( stripos($di_max, 'w') !== false ) {  // week  2018w42
    $gendate = new DateTime();
    $gendate->setISODate(substr($di_max,0,4),substr($di_max,5,2),7);
    $norm_max = $gendate->format('Y-m-d');
  }
  elseif ( stripos($di_max, '-') !== false || ( strlen($di_max) == 8 && strpos($di_max, "-") === false ) ) {
    if ( strpos($di_max, "-") === false ) {
      $norm_max = substr($di_max,0,4)."-".substr($di_max,4,2)."-".substr($di_max,6,2);
    }
    else {
      $norm_max = $di_max;
  }
  }  // day
  else {
  $norm_max = $di_max_date;
  }


  $max_type = max_types($norm_max);

  if ( $preserve_int == 1 ) {
  //change di min to int format
  if ( $di_int == "y" ) { $max = $max_type['y']; }
  if ( $di_int == "q" ) { $max = $max_type['q']; }
  if ( $di_int == "m" ) { $max = $max_type['m']; }
  if ( $di_int == "w" ) { $max = $max_type['w']; }
  if ( $di_int == "d" ) { $max = $max_type['d']; }
  }
  else {
  $max = $max_type[$di_int];
  }

  //$di_max = $norm_max;
  $di_max_type = max_types($max);
  $di_max = $di_max_type[$di_int];

  // cannot go prior to 2018
  //$di_earliest = date_create('2018-01-01');

  if ( isset($this->params['request']['di_display']) ) { $di_display = $this->params['request']['di_display']; } else { $di_display = '000'; }
  if ( isset($this->params['request']['di_first']) ) { $di_first = date_create($this->params['request']['di_first']); } else { $di_first = $di_first_date; }
  if ( isset($this->params['request']['di_last']) ) { $di_last = date_create($this->params['request']['di_last']); } else { $di_last = new DateTime("now"); }
  $di_last = date_add($di_last, date_interval_create_from_date_string('1 days'));  // add a day to $di_last, to account for beginning and end dates, inclusive.

  $di_first_formatted = $di_first->format("Y-m-d");
  $di_last_formatted = $di_last->format("Y-m-d");

  $diff = date_diff($di_first, $di_last);
  $diff = $diff->format('%a');

  if ( $diff >= 2000 ) { $slider_w = 1500; $slider_right_w = 1600; $slider_right_pct = 50; $slider_scroll = "scroll"; }
  else { $slider_w = 650; $slider_right_w = 700; $slider_right_pct = 100; $slider_scroll = "hidden"; }

  if ( $diff == 0 ) { $diff = 1; }  // prevent div by zero
  $scale = round($slider_w /$diff, 4);

  $daterange = new DatePeriod($di_first, new DateInterval('P1D'), $di_last);

  $i = $j = $j1 = $j2 = $j3 = $j4 = $k = $k1 = $k2 = $k3 = $k4 = 0; $sy = $sq = $sm = $sw = $swl = ""; $q = "";
  $syiw = $sqiw = $smiw = $swiw = $swliw = 0; $syew = $sqew = $smew = $swew = $swlew = 0;
  $syw = $sqw = $smw = $sww = $swlw = 0;
  $onetimey = $onetimeq = $onetimem = $onetimew = $onetimewl = $onetimed = 0;

  foreach($daterange as $date) {

  $slider_values .= '"'.$date->format("Y-m-d") .'",';
  $weeks[$date->format("Y-m-d")] = $date->format("oW");

  if ( $i == 0 ) {   // OPEN INITIAL DIV
    $sy .= '<div class=\"sy syi\">'.$date->format("Y");

    $q = ceil(date($date->format("m"), time()) / 3);
    $sq .= '<div class=\"sq sqi\">Q'.$q;

    // populates selector boxes - this is initial interval
    $w_vals[] = $date->format("o")."W".$date->format("W");
    $w_norm_vals_min[] = $date->format("Y-m-d");

      $m_vals[] = $date->format("Y")."".$date->format("m");
      $m_norm_vals_min[] = $date->format("Y-m-d");

      $q_vals[] = $date->format("Y")."Q".$q;
      $q_norm_vals_min[] = $date->format("Y-m-d");

      $y_vals[] = $date->format("Y");
      $y_norm_vals_min[] = $date->format("Y-m-d");

      $sm .= '<div class=\"sm smi\">'.substr($date->format("m"),0,2);

      $sw .= '<div class=\"sw swi\">';
      $swl .= '<div class=\"swl swil\" style=\"color: rgba(0,0,0,0) !important;\">!'; // placeholder text char keeps divs aligned
    }

    if ( $date->format("N") == 1 ) {   // creates array of year/weeks and the first date of that week

      date_add($date, date_interval_create_from_date_string('-7 days')); // add in prior week, in case of partial week at beginning of year coinciding with date slider begins
      $week_dates_start[$date->format("oW")] = $date->format("Y-m-d");
      date_add($date, date_interval_create_from_date_string('7 days')); // reversion
      $week_dates_start[$date->format("oW")] = $date->format("Y-m-d");
    }
    if ( $date->format("N") == 7 ) {   // creates array of year/weeks and the last date of that week -- again, jquery is undependable in this regard
      $week_dates_end[$date->format("oW")] = $date->format("Y-m-d");
    }

    if ( $i > 0 && $date->format("N") == 1 ) { // ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  WEEK
      $j3 = $i;

      // populates selector boxes
      $w_vals[] = $date->format("o")."W".$date->format("W");
      $w_norm_vals_min[] = $date->format("Y-m-d");
      $w_norm_vals_max[] = date('Y-m-d', strtotime('-1 day', strtotime($date->format("Y-m-d"))));

      $thisy = $date->format("Y");
      $thisy = $thisy % 4;

      if ( $thisy == 0 ) { $leap = $i +1; } else { $leap = $i; }

      $sww = (7 *$scale) -1;

      if ( $onetimew == 0 ) { $swiw = ($leap *$scale) -1; $onetimew++; }

      $sw .= '</div><div class=\"sw sw'.$date->format("W").'\" style=\"width: '.$sww.'px\">';
      $k3++;

      if ( $date->format("W") == 1 || $date->format("W") == 11 || $date->format("W") == 21 || $date->format("W") == 31 || $date->format("W") == 41 ) {
        $j4 = $i;

        if ( $date->format("W") == 41 ) { $leap1 = 84; } else { $leap1 = 70; }

        if ( $date->format("W") == 41 ) {
          // if a year has 53 weeks -- need to add extra week of width to week 41 div
          $dt = new DateTime('December 28th, '.$date->format("Y"));
          if ( $dt->format('W') == 53 ) { $leap1 = 91; }
        }
        $sww = $scalewl = ($leap1 *$scale) -1;

        if ( $onetimewl == 0 ) { $swliw = ($i *$scale) -1; $onetimewl++; }
        $swl .= '</div><div class=\"swl swl'.$k4.'\" style=\"width: '.$sww.'px\">w'.$date->format("W");
        $k4++;
      }
    }

    if ( $i > 0 && $date->format("d") == "01" ) { // ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  MONTH
      $j2 = $i;

      // populates selector boxes
      $m_vals[] = $date->format("Y")."".$date->format("m");
      $m_norm_vals_min[] = $date->format("Y-m-d");
      $m_norm_vals_max[] = date('Y-m-d', strtotime('-1 day', strtotime($date->format("Y-m-d"))));

      $thisy = $date->format("Y");
      $thism = $date->format("m");

      $thisy = $thisy % 4;
      $smw = 31;
      if ( $thism == "02" ) { if ( $thisy == 0 ) { $smw = 29; } else { $smw = 28; } }
      else if ( $thism == "04" || $thism == "06" || $thism == "09" || $thism == "11" ) { $smw = 30; }
      $smw = ($smw *$scale) -1;

      if ( $onetimem == 0 ) { $smiw = ($i *$scale) -1; $onetimem++; }

      $sm .= '</div><div class=\"sm sm'.$k2.'\" style=\"width: '.$smw.'px\">'.substr($date->format("m"),0,2);
      $k2++;
    }
    if ( $i > 0 && $date->format("d") == "01" && ( // ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  QUARTER
    $date->format("m") == "01" ||
    $date->format("m") == "04" ||
    $date->format("m") == "07" ||
    $date->format("m") == "10" )
    ) {
      $j1 = $i;

      if ( $onetimeq == 0 ) { $sqiw = ($i *$scale) -1; $onetimeq++; }
      $q = ceil(date($date->format("m"), time()) / 3);

      // populates selector boxes
      $q_vals[] = $date->format("Y")."Q".$q;
      $q_norm_vals_min[] = $date->format("Y-m-d");
      $q_norm_vals_max[] = date('Y-m-d', strtotime('-1 day', strtotime($date->format("Y-m-d"))));

      if ( $q == 1 ) { $sqw = 90; } else if ( $q == 2 ) { $sqw = 91; } else if ( $q == 3 ) { $sqw = 92; } else { $sqw = 92; }
      $sqw = ($sqw *$scale) -1;
      $sq .= '</div><div class=\"sq sq'.$k1.'\" style=\"width: '.$sqw.'px\">Q'.$q;
      $k1++;
    }
    if ( $i > 0 && $date->format("m") == "01" && $date->format("d") == "01" ) {  // ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  ~  YEAR
      $j = $i;

      // populates selector boxes
      $y_vals[] = $date->format("Y");
      $y_norm_vals_min[] = $date->format("Y-m-d");
      $y_norm_vals_max[] = date('Y-m-d', strtotime('-1 day', strtotime($date->format("Y-m-d"))));

      $thisy = $date->format("Y");

      $thisy = $thisy % 4;
      $syw = 365;
      if ( $thisy == 0 ) { $syw = 366; }
      $syw = ($syw *$scale) -1;

      if ( $onetimey == 0 ) { $syiw = ($i *$scale) -1; $onetimey++; }
      $sy .= '</div><div class=\"sy sy'.$k.'\" style=\"width: '.$syw.'px\">'.$date->format("Y");
      $k++;
    }

    if ( $i == $diff -1 ) {  // CLOSE LAST DIV
      $i++;
      $syew = ($i - $j) *$scale;
      $sqew = ($i - $j1) *$scale;
      $smew = ($i - $j2) *$scale;
      $swew = ($i - $j3) *$scale;
      $swlew = ($i - $j4) *$scale;
      $sy .= '</div>';
      $sq .= '</div>';
      $sm .= '</div>';
      $sw .= '</div>';
      $swl .= '</div>';

      $last_date = $date->format("Y-m-d");

      $y_norm_vals_max[] = date('Y-m-d', strtotime('-1 day', strtotime($date->format("Y-m-d"))));
      $q_norm_vals_max[] = date('Y-m-d', strtotime('-1 day', strtotime($date->format("Y-m-d"))));
      $m_norm_vals_max[] = date('Y-m-d', strtotime('-1 day', strtotime($date->format("Y-m-d"))));
      $w_norm_vals_max[] = date('Y-m-d', strtotime('-1 day', strtotime($date->format("Y-m-d"))));
    }
    $i++;
  };

  $slider_values = substr($slider_values,0,-1);

  $d_val = str_replace('"', '', $slider_values);
  $d_vals = explode(',', $d_val);

  // add an additional numerical week 'end date' -- to cover for partial weeks generated by code above.
  $week_dates_end[$date->format("oW")] = $date->format("m/d/Y");

  $ret = '
  <div name="date_interval">
    <div id="jquery_di_data"></div>
    <div id="slider-left" class="slider-left">
      <div id="slider-select-interval-buttons" class="slider-select-interval-buttons">
        <div id="slider-select-y" class="slider-select-y slider-select-interval">Y</div>
        <div id="slider-select-q" class="slider-select-q slider-select-interval">Q</div>
        <div id="slider-select-m" class="slider-select-m slider-select-interval">M</div>
        <div id="slider-select-w" class="slider-select-w slider-select-interval">W</div>
        <div id="slider-select-d" class="slider-select-d slider-select-interval">D</div>
      </div>
      <div id="slider-result" class="slider-result">
        <div id="slider-result-min" class="slider-result-min">
          <div id="di_min_shown"></div>
          <select name="di_min_y" id="di_min_y" size="5" class="gui_select_fleft_select di-min-selector di-min-y"></select>
          <select name="di_min_q" id="di_min_q" size="5" class="gui_select_fleft_select di-min-selector di-min-q"></select>
          <select name="di_min_m" id="di_min_m" size="5" class="gui_select_fleft_select di-min-selector di-min-m"></select>
          <select name="di_min_w" id="di_min_w" size="5" class="gui_select_fleft_select di-min-selector di-min-w"></select>
          <select name="di_min_d" id="di_min_d" size="5" class="gui_select_fleft_select di-min-selector di-min-d"></select>
        </div>
        <div id="slider-result-to" class="slider-result-to">to</div>
        <div id="slider-result-max" class="slider-result-max">
          <div id="di_max_shown"></div>
          <select name="di_max_y" id="di_max_y" size="5" class="gui_select_fleft_select di-max-selector di-max-y"></select>
          <select name="di_max_q" id="di_max_q" size="5" class="gui_select_fleft_select di-max-selector di-max-q"></select>
          <select name="di_max_m" id="di_max_m" size="5" class="gui_select_fleft_select di-max-selector di-max-m"></select>
          <select name="di_max_w" id="di_max_w" size="5" class="gui_select_fleft_select di-max-selector di-max-w"></select>
          <select name="di_max_d" id="di_max_d" size="5" class="gui_select_fleft_select di-max-selector di-max-d"></select>
        </div>
      </div>
    </div>
    <div id="slider-select-interval-labels" class="slider-select-interval-labels">
      <div id="slider-select-y-label" class="slider-select-y-label slider-select-interval-label">Interval: &nbsp; <b>Year</b></div>
      <div id="slider-select-q-label" class="slider-select-q-label slider-select-interval-label">Interval: &nbsp; <b>Quarter</b></div>
      <div id="slider-select-m-label" class="slider-select-m-label slider-select-interval-label">Interval: &nbsp; <b>Month</b></div>
      <div id="slider-select-w-label" class="slider-select-w-label slider-select-interval-label">Interval: &nbsp; <b>Week</b></div>
      <div id="slider-select-d-label" class="slider-select-d-label slider-select-interval-label">Interval: &nbsp; <b>Day</b></div>
    </div>

    <div id="slider-scroll" class="slider-scroll">
      <div id="slider-wrapper" class="slider-wrapper">
        <div id="top-slider-label" class="top-slider-label"></div>
        <div id="slider-right" class="slider-right">
          <input type="text" id="slider" class="slider">  <!-- slider is inserted here -->
        </div>
        <div class="bottom-slider-label"></div>
      </div>
    </div>
  </div>
  <div id="currentPos"></div>
  <div id="logPos"></div>
  <!--<div id="slider-result-technical">
  <div id="slider-result-technical-min" class="slider-result-technical-min"></div> to <div id="slider-result-technical-max" class="slider-result-technical-max"></div>
  </div>
  -->
  ';

  $this->jq1 = '

  var weeks = '.json_encode($weeks, JSON_PRETTY_PRINT).';
  var week_dates_start = '.json_encode($week_dates_start, JSON_PRETTY_PRINT).';
  var week_dates_end = '.json_encode($week_dates_end, JSON_PRETTY_PRINT).';

  var y_vals = '.json_encode($y_vals, JSON_PRETTY_PRINT).';
  var q_vals = '.json_encode($q_vals, JSON_PRETTY_PRINT).';
  var m_vals = '.json_encode($m_vals, JSON_PRETTY_PRINT).';
  var w_vals = '.json_encode($w_vals, JSON_PRETTY_PRINT).';
  var d_vals = '.json_encode($d_vals, JSON_PRETTY_PRINT).';

  var y_norm_vals_min = '.json_encode($y_norm_vals_min, JSON_PRETTY_PRINT).';
  var q_norm_vals_min = '.json_encode($q_norm_vals_min, JSON_PRETTY_PRINT).';
  var m_norm_vals_min = '.json_encode($m_norm_vals_min, JSON_PRETTY_PRINT).';
  var w_norm_vals_min = '.json_encode($w_norm_vals_min, JSON_PRETTY_PRINT).';

  var y_norm_vals_max = '.json_encode($y_norm_vals_max, JSON_PRETTY_PRINT).';
  var q_norm_vals_max = '.json_encode($q_norm_vals_max, JSON_PRETTY_PRINT).';
  var m_norm_vals_max = '.json_encode($m_norm_vals_max, JSON_PRETTY_PRINT).';
  var w_norm_vals_max = '.json_encode($w_norm_vals_max, JSON_PRETTY_PRINT).';

  var min_type = '.json_encode($min_type, JSON_PRETTY_PRINT).';
  var max_type = '.json_encode($max_type, JSON_PRETTY_PRINT).';

  var di_int = "'.$di_int.'";
  var min = "'.$min.'";
  var max = "'.$max.'";
  var di_min = "'.$di_min.'";
  var di_max = "'.$di_max.'";
  var di_display = "'.$di_display.'";
  var di_first_formatted = "'.$di_first_formatted.'";
  var di_last_formatted = "'.$di_last_formatted.'";

  // add data to selector boxes
  $.each(y_vals, function(i, v){
    $("#di_min_y").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
    $("#di_max_y").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
  });
  $.each(q_vals, function(i, v){
    $("#di_min_q").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
    $("#di_max_q").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
  });
  $.each(m_vals, function(i, v){
    $("#di_min_m").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
    $("#di_max_m").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
  });
  $.each(w_vals, function(i, v){
    $("#di_min_w").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
    $("#di_max_w").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
  });
  $.each(d_vals, function(i, v){
    $("#di_min_d").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
    $("#di_max_d").append("<option value=\"" +v+ "\" data-i=\"" +i+ "\">" +v+ "</option>");
  });

  // hide all selector options until needed (call up by css)
  $("#di_min_y").css("visibility","hidden");
  $("#di_min_q").css("visibility","hidden");
  $("#di_min_m").css("visibility","hidden");
  $("#di_min_w").css("visibility","hidden");
  $("#di_min_d").css("visibility","hidden");

  $("#di_max_y").css("visibility","hidden");
  $("#di_max_q").css("visibility","hidden");
  $("#di_max_m").css("visibility","hidden");
  $("#di_max_w").css("visibility","hidden");
  $("#di_max_d").css("visibility","hidden");

  $(".top-slider-label").append("<div id=\"scale-years\" class=\"scale-years\">'.$sy.'</div><div id=\"scale-quarters\" class=\"scale-quarters\">'.$sq.'</div><div id=\"scale-months\" class=\"scale-months\">'.$sm.'</div>");
  $(".bottom-slider-label").append("<div id=\"scale-weeks\" class=\"scale-weeks\">'.$sw.'</div><div id=\"scale-weeksl\" class=\"scale-weeksl\">'.$swl.'</div>");

  // toggle display of interval buttons
  if ( di_display == "000" ) {}
  else {
    if ( di_display.indexOf("y") == -1 ) { $("#slider-select-y").css("display","none"); }
    if ( di_display.indexOf("q") == -1 ) { $("#slider-select-q").css("display","none"); }
    if ( di_display.indexOf("m") == -1 ) { $("#slider-select-m").css("display","none"); }
    if ( di_display.indexOf("w") == -1 ) { $("#slider-select-w").css("display","none"); }
    if ( di_display.indexOf("d") == -1 ) { $("#slider-select-d").css("display","none"); }
  }

  $(".sy:eq('.$k.')").addClass("sye"); // add class to last div in each interval scale row
  $(".sq:eq('.$k1.')").addClass("sqe");
  $(".sm:eq('.$k2.')").addClass("sme");
  $(".sw:eq('.$k3.')").addClass("swe");
  $(".swl:eq('.$k4.')").addClass("swel");

  $(".syi").css("width","'.$syiw.'");  // add in widths to first and last interval scale row divs
  $(".sye").css("width","'.$syew.'");

  $(".sqi").css("width","'.$sqiw.'");
  $(".sqe").css("width","'.$sqew.'");

  $(".smi").css("width","'.$smiw.'");
  $(".sme").css("width","'.$smew.'");

  $(".swi").css("width","'.$swiw.'");
  $(".swe").css("width","'.$swew.'");

  $(".swil").css("width","'.$swliw.'");
  $(".swel").css("width","'.$swlew.'");

  $(".sq:contains(\"Q4\")").css("border-right","1px solid rgba(0,0,0,0.25)"); // highlight specific scale lines
  $(".sm:contains(\"12\")").css("border-right","1px solid rgba(0,0,0,0.25)");
  $(".sw01").prev().addClass("sw-last-week");
  $(".swl:contains(\"w01\")").css("border-left","1px solid rgba(0,0,0,0.25)");

  $(".sye, .sqe, .sme, .swe, .swel").css("border-right", "0px solid rgba(255,255,255,0.8)")  // remove last line in each interval scale row

  // clean up first and last item in each interval scale row, removing descriptive text, if space is too small.
  if ( '.$syiw.' <= '.$syw.' /12 ) {  $(".top-slider-label .syi").css("color","rgba(0,0,0,0)"); }
  if ( '.$sqiw.' <= '.$sqw.' /8 ) {  $(".top-slider-label .sqi").css("color","rgba(0,0,0,0)"); }
  if ( '.$smiw.' <= '.$smw.' /4 ) {  $(".top-slider-label .smi").css("color","rgba(0,0,0,0)"); }
  if ( '.$swliw.' <= '.$sww.' /2 ) {  $(".top-slider-label .swil").css("color","rgba(0,0,0,0)"); }

  if ( '.$syew.' <= '.$syw.' /12 ) {  $(".top-slider-label .sye").css("color","rgba(0,0,0,0)"); }
  if ( '.$sqew.' <= '.$sqw.' /8 ) {  $(".top-slider-label .sqe").css("color","rgba(0,0,0,0)"); }
  if ( '.$smew.' <= '.$smw.' /4 ) {  $(".top-slider-label .sme").css("color","rgba(0,0,0,0)"); }
  if ( '.$swlew.' <= '.$sww.' /2 ) {  $(".top-slider-label .swel").css("color","rgba(0,0,0,0)"); }

  $(".sm").addClass("selected-scale");

  var mySlider = new rSlider({   // actual slider
    target: "#slider",
    values: ['.$slider_values.'],
    //values:  {min: 20180101, max: 20181231}, // stepping only works with an object,
    //but dates do not work like regular base 10 numbers, unless you use regular numbering and have a lookup table
    range: true,
    width: "'.$slider_w.'",
    labels: false,
    scale: false,
    //step: 10,
    tooltip: false
    //,set: [di_min,di_max]
  });

  mySlider.setValues(di_min,di_max);  // for some reason it does not set the min and max properly when in the initial slider create, thus it is set here

  $("#slider-scroll").css("overflow-x","hidden");

  $("#slider-right .rs-container .rs-pointer[data-dir=left]").prop("id", "rs-pointer-left");  // add extra "id"s
  $("#slider-right .rs-container .rs-pointer[data-dir=right]").prop("id", "rs-pointer-right");

  $("#date_interval_div_box").css("opacity", "1");  // widget built, make visible and hide until needed
  $("#date_interval_div_box").css("display", "none");

  $("#slider-select-y-label").hide();
  $("#slider-select-q-label").hide();
  $("#slider-select-m-label").hide();
  $("#slider-select-w-label").hide();
  $("#slider-select-d-label").hide();

  // which time increment label to highlight
  if ( di_int == "y" ) { $("#slider-select-y-label").show(); $("#slider-select-y").addClass("slider-select-active"); }
  if ( di_int == "q" ) { $("#slider-select-q-label").show(); $("#slider-select-q").addClass("slider-select-active"); }
  if ( di_int == "m" ) { $("#slider-select-m-label").show(); $("#slider-select-m").addClass("slider-select-active"); }
  if ( di_int == "w" ) { $("#slider-select-w-label").show(); $("#slider-select-w").addClass("slider-select-active"); }
  if ( di_int == "d" ) { $("#slider-select-d-label").show(); $("#slider-select-d").addClass("slider-select-active"); }

  // set vars in hidden fields
  $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_int\" name=\"di_int\" value=\""+di_int+"\">");
  $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_min\" name=\"di_min\" value=\""+di_min+"\">");  // this is also set below, keep this as it is used initially
  $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_max\" name=\"di_max\" value=\""+di_max+"\">");  // this is also set below, keep this as it is used initially
  $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_first\" name=\"di_first\" value=\""+di_first_formatted+"\">");
  $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_last\" name=\"di_last\" value=\""+di_last_formatted+"\">");
  $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_display\" name=\"di_display\" value=\""+di_display+"\">");


  // highlight min and max in selector boxes and set them into view in selector
  $("#di_min_y option[value="+min_type.y+"]").attr("selected", true);
  $("#di_max_y option[value="+max_type.y+"]").attr("selected", true);

  $("#di_min_q option[value="+min_type.q+"]").attr("selected", true);
  $("#di_max_q option[value="+max_type.q+"]").attr("selected", true);

  $("#di_min_m option[value="+min_type.m+"]").attr("selected", true);
  $("#di_max_m option[value="+max_type.m+"]").attr("selected", true);

  $("#di_min_w option[value="+min_type.w+"]").attr("selected", true);
  $("#di_max_w option[value="+max_type.w+"]").attr("selected", true);

  $("#di_min_d option[value="+min_type.d+"]").attr("selected", true);
  $("#di_max_d option[value="+max_type.d+"]").attr("selected", true);


  $(document).ready(function() {
    var min = "", max = "", l_min = "", l_max = "";
    parse_values(min,max,l_min,l_max);

    $("#di_min_y").on("click", function() {
      var sel = $("#di_min_y").find(":selected").text();
      var seli = y_vals.indexOf(sel);

      $("#jquery_di_data #di_min").val(y_vals[seli]);
      $("#jquery_di_data #di_min_norm").val(y_norm_vals_min[seli]);

      var date_max = $("#jquery_di_data #di_max").val();
      var new_date_min = y_vals[seli];

      var date_max_norm = $("#jquery_di_data #di_max_norm").val();
      var new_date_min_norm = y_norm_vals_min[seli];

      if ( new_date_min > date_max ) {
        new_date_min = date_max;
        seli = y_vals.indexOf(new_date_min);
        new_date_min_norm = y_norm_vals_min[seli];
      }

      mySlider.setValues(new_date_min_norm,date_max_norm);

      // select the new one
      $("#di_min_y option:selected").removeAttr("selected");
      $("#di_min_y").val(new_date_min);

      $("#di_min_shown").text(new_date_min);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });

    $("#di_max_y").on("click", function() {
      var sel = $("#di_max_y").find(":selected").text();
      var seli = y_vals.indexOf(sel);

      $("#jquery_di_data #di_max").val(y_vals[seli]);
      $("#jquery_di_data #di_max_norm").val(y_norm_vals_max[seli]);

      var date_min = $("#jquery_di_data #di_min").val();
      var new_date_max = y_vals[seli];

      var date_min_norm = $("#jquery_di_data #di_min_norm").val();
      var new_date_max_norm = y_norm_vals_max[seli];

      if ( new_date_max < date_min ) {
        new_date_max = date_min;
        seli = y_vals.indexOf(new_date_max);
        new_date_max_norm = y_norm_vals_max[seli];
      }

      mySlider.setValues(date_min_norm,new_date_max_norm);

      // select the new one
      $("#di_max_y option:selected").removeAttr("selected");
      $("#di_max_y").val(new_date_max);

      $("#di_max_shown").text(new_date_max);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });



    $("#di_min_q").on("click", function() {
      var sel = $("#di_min_q").find(":selected").text();
      var seli = q_vals.indexOf(sel);

      $("#jquery_di_data #di_min").val(q_vals[seli]);
      $("#jquery_di_data #di_min_norm").val(q_norm_vals_min[seli]);

      var date_max = $("#jquery_di_data #di_max").val();
      var new_date_min = q_vals[seli];

      var date_max_norm = $("#jquery_di_data #di_max_norm").val();
      var new_date_min_norm = q_norm_vals_min[seli];

      if ( new_date_min > date_max ) {
        new_date_min = date_max;
        seli = q_vals.indexOf(new_date_min);
        new_date_min_norm = q_norm_vals_min[seli];
      }

      mySlider.setValues(new_date_min_norm,date_max_norm);

      // select the new one
      $("#di_min_q option:selected").removeAttr("selected");
      $("#di_min_q").val(new_date_min);

      $("#di_min_shown").text(new_date_min);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });

    $("#di_max_q").on("click", function() {
      var sel = $("#di_max_q").find(":selected").text();
      var seli = q_vals.indexOf(sel);

      $("#jquery_di_data #di_max").val(q_vals[seli]);
      $("#jquery_di_data #di_max_norm").val(q_norm_vals_max[seli]);

      var date_min = $("#jquery_di_data #di_min").val();
      var new_date_max = q_vals[seli];

      var date_min_norm = $("#jquery_di_data #di_min_norm").val();
      var new_date_max_norm = q_norm_vals_max[seli];

      if ( new_date_max < date_min ) {
        new_date_max = date_min;
        seli = q_vals.indexOf(new_date_max);
        new_date_max_norm = q_norm_vals_max[seli];
      }

      mySlider.setValues(date_min_norm,new_date_max_norm);

      // select the new one
      $("#di_max_q option:selected").removeAttr("selected");
      $("#di_max_q").val(new_date_max);

      $("#di_max_shown").text(new_date_max);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });



    $("#di_min_m").on("click", function() {
      var sel = $("#di_min_m").find(":selected").text();
      var seli = m_vals.indexOf(sel);

      $("#jquery_di_data #di_min").val(m_vals[seli]);
      $("#jquery_di_data #di_min_norm").val(m_norm_vals_min[seli]);

      var date_max = $("#jquery_di_data #di_max").val();
      var new_date_min = m_vals[seli];

      var date_max_norm = $("#jquery_di_data #di_max_norm").val();
      var new_date_min_norm = m_norm_vals_min[seli];

      if ( new_date_min > date_max ) {
        new_date_min = date_max;
        seli = m_vals.indexOf(new_date_min);
        new_date_min_norm = m_norm_vals_min[seli];
      }

      mySlider.setValues(new_date_min_norm,date_max_norm);

      // select the new one
      $("#di_min_m option:selected").removeAttr("selected");
      $("#di_min_m").val(new_date_min);

      $("#di_min_shown").text(new_date_min);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });

    $("#di_max_m").on("click", function() {
      var sel = $("#di_max_m").find(":selected").text();
      var seli = m_vals.indexOf(sel);

      $("#jquery_di_data #di_max").val(m_vals[seli]);
      $("#jquery_di_data #di_max_norm").val(m_norm_vals_max[seli]);

      var date_min = $("#jquery_di_data #di_min").val();
      var new_date_max = m_vals[seli];

      var date_min_norm = $("#jquery_di_data #di_min_norm").val();
      var new_date_max_norm = m_norm_vals_max[seli];

      if ( new_date_max < date_min ) {
        new_date_max = date_min;
        seli = m_vals.indexOf(new_date_max);
        new_date_max_norm = m_norm_vals_max[seli];
      }

      mySlider.setValues(date_min_norm,new_date_max_norm);

      // select the new one
      $("#di_max_m option:selected").removeAttr("selected");
      $("#di_max_m").val(new_date_max);

      $("#di_max_shown").text(new_date_max);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });


    $("#di_min_w").on("click", function() {
      var sel = $("#di_min_w").find(":selected").text();
      var seli = w_vals.indexOf(sel);

      $("#jquery_di_data #di_min").val(w_vals[seli]);
      $("#jquery_di_data #di_min_norm").val(w_norm_vals_min[seli]);

      var date_max = $("#jquery_di_data #di_max").val();
      var new_date_min = w_vals[seli];

      var date_max_norm = $("#jquery_di_data #di_max_norm").val();
      var new_date_min_norm = w_norm_vals_min[seli];

      if ( new_date_min > date_max ) {
        new_date_min = date_max;
        seli = w_vals.indexOf(new_date_min);
        new_date_min_norm = w_norm_vals_min[seli];
      }

      mySlider.setValues(new_date_min_norm,date_max_norm);

      // select the new one
      $("#di_min_w option:selected").removeAttr("selected");
      $("#di_min_w").val(new_date_min);

      $("#di_min_shown").text(new_date_min);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });

    $("#di_max_w").on("click", function() {
      var sel = $("#di_max_w").find(":selected").text();
      var seli = w_vals.indexOf(sel);

      $("#jquery_di_data #di_max").val(w_vals[seli]);
      $("#jquery_di_data #di_max_norm").val(w_norm_vals_max[seli]);

      var date_min = $("#jquery_di_data #di_min").val();
      var new_date_max = w_vals[seli];

      var date_min_norm = $("#jquery_di_data #di_min_norm").val();
      var new_date_max_norm = w_norm_vals_max[seli];

      if ( new_date_max < date_min ) {
        new_date_max = date_min;
        seli = w_vals.indexOf(new_date_max);
        new_date_max_norm = w_norm_vals_max[seli];
      }

      mySlider.setValues(date_min_norm,new_date_max_norm);

      // select the new one
      $("#di_max_w option:selected").removeAttr("selected");
      $("#di_max_w").val(new_date_max);

      $("#di_max_shown").text(new_date_max);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });


    $("#di_min_d").on("click", function() {
      var sel = $("#di_min_d").find(":selected").text();
      var seli = d_vals.indexOf(sel);

      $("#jquery_di_data #di_min").val(d_vals[seli]);
      $("#jquery_di_data #di_min_norm").val(d_vals[seli]);

      var date_max = $("#jquery_di_data #di_max").val();
      var new_date_min = d_vals[seli];

      var date_max_norm = $("#jquery_di_data #di_max_norm").val();
      var new_date_min_norm = d_vals[seli];

      if ( new_date_min > date_max ) {
        new_date_min = date_max;
        seli = d_vals.indexOf(new_date_min);
        new_date_min_norm = d_vals[seli];
      }

      mySlider.setValues(new_date_min_norm,date_max_norm);

      // select the new one
      $("#di_min_d option:selected").removeAttr("selected");
      $("#di_min_d").val(new_date_min);

      $("#di_min_shown").text(new_date_min);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });

    $("#di_max_d").on("click", function() {
      var sel = $("#di_max_d").find(":selected").text();
      var seli = d_vals.indexOf(sel);

      $("#jquery_di_data #di_max").val(d_vals[seli]);
      $("#jquery_di_data #di_max_norm").val(d_vals[seli]);

      var date_min = $("#jquery_di_data #di_min").val();
      var new_date_max = d_vals[seli];

      var date_min_norm = $("#jquery_di_data #di_min_norm").val();
      var new_date_max_norm = d_vals[seli];

      if ( new_date_max < date_min ) {
        new_date_max = date_min;
        seli = d_vals.indexOf(new_date_max);
        new_date_max_norm = d_vals[seli];
      }

      mySlider.setValues(date_min_norm,new_date_max_norm);

      // select the new one
      $("#di_max_d option:selected").removeAttr("selected");
      $("#di_max_d").val(new_date_max);

      $("#di_max_shown").text(new_date_max);

      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);
    });

    // which selector set to show
    $("#di_min_shown").on("mouseover", function() {
      var di_int = $("#jquery_di_data #di_int").val();
      if ( di_int == "y" ) { $("#di_min_y").css("visibility","visible"); }
      if ( di_int == "q" ) { $("#di_min_q").css("visibility","visible"); }
      if ( di_int == "m" ) { $("#di_min_m").css("visibility","visible"); }
      if ( di_int == "w" ) { $("#di_min_w").css("visibility","visible"); }
      if ( di_int == "d" ) { $("#di_min_d").css("visibility","visible"); }
    });
    $("#di_max_shown").on("mouseover", function() {
      var di_int = $("#jquery_di_data #di_int").val();
      if ( di_int == "y" ) { $("#di_max_y").css("visibility","visible"); }
      if ( di_int == "q" ) { $("#di_max_q").css("visibility","visible"); }
      if ( di_int == "m" ) { $("#di_max_m").css("visibility","visible"); }
      if ( di_int == "w" ) { $("#di_max_w").css("visibility","visible"); }
      if ( di_int == "d" ) { $("#di_max_d").css("visibility","visible"); }
    });

    $("#di_min_y").on("mouseleave", function() { $("#di_min_y").css("visibility","hidden"); });
    $("#di_max_y").on("mouseleave", function() { $("#di_max_y").css("visibility","hidden"); });

    $("#di_min_q").on("mouseleave", function() { $("#di_min_q").css("visibility","hidden"); });
    $("#di_max_q").on("mouseleave", function() { $("#di_max_q").css("visibility","hidden"); });

    $("#di_min_m").on("mouseleave", function() { $("#di_min_m").css("visibility","hidden"); });
    $("#di_max_m").on("mouseleave", function() { $("#di_max_m").css("visibility","hidden"); });

    $("#di_min_w").on("mouseleave", function() { $("#di_min_w").css("visibility","hidden"); });
    $("#di_max_w").on("mouseleave", function() { $("#di_max_w").css("visibility","hidden"); });

    $("#di_min_d").on("mouseleave", function() { $("#di_min_d").css("visibility","hidden"); });
    $("#di_max_d").on("mouseleave", function() { $("#di_max_d").css("visibility","hidden"); });


    $("#slider-select-y").click(function() {
      $("#slider-select-y-label").show();
      $("#slider-select-q-label").hide();
      $("#slider-select-m-label").hide();
      $("#slider-select-w-label").hide();
      $("#slider-select-d-label").hide();

      $(".slider-select-interval").css("border","1px solid #fff");

      $(".slider-select-interval").removeClass("slider-select-active");
      $("#slider-select-y").addClass("slider-select-active");

      $("#slider-select-y").css("border","1px solid rgb(57,73,172)");

      $("#jquery_di_data #di_int").remove();
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_int\" name=\"di_int\" value=\"y\">");

      $(".sy, .sq, .sm, .swl").removeClass("selected-scale");
      $(".sy").addClass("selected-scale");
    });
    $("#slider-select-q").click(function() {
      $("#slider-select-y-label").hide();
      $("#slider-select-q-label").show();
      $("#slider-select-m-label").hide();
      $("#slider-select-w-label").hide();
      $("#slider-select-d-label").hide();

      $(".slider-select-interval").css("border","1px solid #fff");

      $(".slider-select-interval").removeClass("slider-select-active");
      $("#slider-select-q").addClass("slider-select-active");

      $("#slider-select-q").css("border","1px solid rgb(57,73,172)");

      $("#jquery_di_data #di_int").remove();
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_int\" name=\"di_int\" value=\"q\">");

      $(".sy, .sq, .sm, .swl").removeClass("selected-scale");
      $(".sq").addClass("selected-scale");
    });
    $("#slider-select-m").click(function() {
      $("#slider-select-y-label").hide();
      $("#slider-select-q-label").hide();
      $("#slider-select-m-label").show();
      $("#slider-select-w-label").hide();
      $("#slider-select-d-label").hide();

      $(".slider-select-interval").css("border","1px solid #fff");

      $(".slider-select-interval").removeClass("slider-select-active");
      $("#slider-select-m").addClass("slider-select-active");

      $("#slider-select-m").css("border","1px solid rgb(57,73,172)");

      $("#jquery_di_data #di_int").remove();
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_int\" name=\"di_int\" value=\"m\">");

      $(".sy, .sq, .sm, .swl").removeClass("selected-scale");
      $(".sm").addClass("selected-scale");
    });
    $("#slider-select-w").click(function() {
      $("#slider-select-y-label").hide();
      $("#slider-select-q-label").hide();
      $("#slider-select-m-label").hide();
      $("#slider-select-w-label").show();
      $("#slider-select-d-label").hide();

      $(".slider-select-interval").css("border","1px solid #fff");

      $(".slider-select-interval").removeClass("slider-select-active");
      $("#slider-select-w").addClass("slider-select-active");

      $("#slider-select-w").css("border","1px solid rgb(57,73,172)");

      $("#jquery_di_data #di_int").remove();
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_int\" name=\"di_int\" value=\"w\">");

      $(".sy, .sq, .sm, .swl").removeClass("selected-scale");
      $(".swl").addClass("selected-scale");
    });
    $("#slider-select-d").click(function() {
      $("#slider-select-y-label").hide();
      $("#slider-select-q-label").hide();
      $("#slider-select-m-label").hide();
      $("#slider-select-w-label").hide();
      $("#slider-select-d-label").show();

      $(".slider-select-interval").css("border","1px solid #fff");

      $(".slider-select-interval").removeClass("slider-select-active");
      $("#slider-select-d").addClass("slider-select-active");

      $("#slider-select-d").css("border","1px solid rgb(57,73,172)");

      $("#jquery_di_data #di_int").remove();
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_int\" name=\"di_int\" value=\"d\">");

      $(".sy, .sq, .sm, .swl").removeClass("selected-scale");
      //$(".swl").addClass("selected-scale");
    });

    function update_slider() {
      var date_min = $("#jquery_di_data #di_min_norm").val();
      var date_max = $("#jquery_di_data #di_max_norm").val();

      mySlider.setValues(date_min,date_max);
    }
    //  setInterval(update_slider, 1000);

    $(".slider-right").add(".slider-select-y").add(".slider-select-q").add(".slider-select-m").add(".slider-select-w").add(".slider-select-d").add(".rs-container").on("change click mousemove mouseout mouseleave mouseup focusout",function() {
      var min = "", max = "", l_min = "", l_max = "";
      parse_values(min,max,l_min,l_max);

    });


    $(".slider-right").on("change click",function() {
    //  var values = mySlider.getValue();
    //  var date_interval = $("#jquery_di_data #di_int").val();
    //
    //  var value = values.split(",");
    //
    //  // select the new one
    //  var value0 = m_norm_vals_min.indexOf(value[0]);
    //  var value1 = m_norm_vals_max.indexOf(value[1]);
    //
    //  $("#di_min_m option[data-i=value0]").attr("selected", true);
    //  $("#di_max_m option[data-i=value1]").attr("selected", true);
    //
    //  var seli = m_norm_vals_min.indexOf(value[0]);
    //  $("#jquery_di_data #di_min").val(m_vals[seli]);
    //
    //  var seli = m_norm_vals_max.indexOf(value[1]);
    //  $("#jquery_di_data #di_max").val(m_vals[seli]);
    //
    //  $("#jquery_di_data #di_min_norm").val(value[0]);
    //  $("#jquery_di_data #di_max_norm").val(value[1]);
    //
    //  //  var date_max = $("#jquery_di_data #di_max_norm").val();
    //  //  mySlider.setValues(m_norm_vals_min[seli],di_max);
    //
    //  $("#di_min_shown").text(value[0]);
    //  $("#di_max_shown").text(value[1]);
    //  //  $("#di_min_m").css("display","none");

      parse_values(min,max,l_min,l_max);
    });


    function parse_values(min,max,l_min,l_max) {
      var values = mySlider.getValue();
      var date_interval = $("#jquery_di_data #di_int").val();
      var value = values.split(",");
      var y_min = value[0].slice(0,4); var y_max = value[1].slice(0,4);
      var m_min = value[0].slice(5,7); var m_max = value[1].slice(5,7);

      if ( date_interval == "y" ) { min = y_min; max = y_max; l_min = min; l_max = max; }
      else if ( date_interval == "q" ) {
        if ( m_min <= 3 ) { min = 1; } else if ( m_min >= 4 && m_min <= 6 ) { min = 2; } else if ( m_min >= 7 && m_min <= 9 ) { min = 3; } else { min = 4; }
        if ( m_max <= 3 ) { max = 1; } else if ( m_max >= 4 && m_max <= 6 ) { max = 2; } else if ( m_max >= 7 && m_max <= 9 ) { max = 3; } else { max = 4; }

        l_min = y_min+ " - Quarter " +min; l_max = y_max+ " - Quarter " +max;
        min = y_min+ "Q" +min; max = y_max+ "Q" +max;
      }
      else if ( date_interval == "m" ) { min = m_min; max = m_max; l_min = y_min+ "/" +min; l_max = y_max+ "/" +max; min = y_min+min; max = y_max+max; }
      else if ( date_interval == "w" ) {
        var w_min = "", w_max = "";

        // MIN
        var date = value[0];
        w_min = weeks[date];
        var first_date = week_dates_start[w_min];
        min = w_min.slice(0,4)+ "W" +w_min.slice(4,6);
        var last_date = week_dates_end[w_min];
        l_min = w_min.slice(0,4)+ " - Week " +w_min.slice(4,6)+ ": " +first_date+ " to " +last_date;

        // MAX
        var date = value[1];
        w_max = weeks[date];
        var first_date = week_dates_start[w_max];
        max = w_max.slice(0,4)+ "W" +w_max.slice(4,6);
        var last_date = week_dates_end[w_max];
        l_max = w_max.slice(0,4)+ " - Week " +w_max.slice(4,6)+ ": " +first_date+ " to " +last_date;
      }
      else if ( date_interval == "d" ) { l_min = value[0]; l_max = value[1]; min = l_min; max = l_max; }
      else { var slider_dates = ""; }

      $("#di_min_shown").text(""+min+"");
      $("#di_max_shown").text(""+max+"");

      $("#di_min_y option:selected").removeAttr("selected");
      $("#di_min_q option:selected").removeAttr("selected");
      $("#di_min_m option:selected").removeAttr("selected");
      $("#di_min_w option:selected").removeAttr("selected");
      $("#di_min_d option:selected").removeAttr("selected");
      $("#di_min_y").val(min);
      $("#di_min_q").val(min);
      $("#di_min_m").val(min);
      $("#di_min_w").val(min);
      $("#di_min_d").val(min);

      $("#di_max_y option:selected").removeAttr("selected");
      $("#di_max_q option:selected").removeAttr("selected");
      $("#di_max_m option:selected").removeAttr("selected");
      $("#di_max_w option:selected").removeAttr("selected");
      $("#di_max_d option:selected").removeAttr("selected");
      $("#di_max_y").val(max);
      $("#di_max_q").val(max);
      $("#di_max_m").val(max);
      $("#di_max_w").val(max);
      $("#di_max_d").val(max);

      $("#jquery_di_data #di_min").remove();
      $("#jquery_di_data #di_max").remove();
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_min\" name=\"di_min\" value=\""+min+"\">");
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_max\" name=\"di_max\" value=\""+max+"\">");

      $("#jquery_di_data #di_min_norm").remove();
      $("#jquery_di_data #di_max_norm").remove();
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_min_norm\" name=\"di_min_norm\" value=\""+value[0]+"\">");
      $("#jquery_di_data").append("<input type=\"hidden\" id=\"di_max_norm\" name=\"di_max_norm\" value=\""+value[1]+"\">");

      if ( min == max ) { var textl = min; } else { var textl = min+" to "+max; }
      $("#date_interval span").text(textl);

    }
  });
  ';

  return $ret;
  } // end of gen_html_date_interval_select function


  /**
  * Generates jquery code to be placed at the end of viewer.
  *
  * @return string generated jquery
  */
  public function gen_date_interval_js(){


  $ret = '

  if(DetectIE()){
    $("#slider").keydown(function(){$("#slider").change()});
    $("#slider").keyup(function(){$("#slider").change()});
  }
  // this make sure that IE6 refreshes selected items correctly on click
  if(DetectIE6()){
    $("#slider").click(function(){
      $("#date_interval_div_box").click();
      $("#slider").focus();
    });
  }

  // Handles the submit button
  $("#di_submit").click(function(){
    DoSubmission(true);
    $("#date_interval").click();
  });

  // update title when page loads
  $("#date_interval span").html(" -- ");
  $("#slider").change();

  $("#container").prepend("<div id=\"dialog\" title=\"Dates & Intervals Note\"><p>"+errs+"</p></div>");
  $("#dialog").css("display","none");

  $(document).ready(function() {
    if ( showerr == 1 ) {
      $("#dialog").css("display","block");
    }

    $("#date_interval_dialog_close").on("click",function() {
      $("#dialog").css("display","none");
    });
  });

  '.$this->jq1.'

  ';

  return $ret;
  }

}  // end date interval select class
?>