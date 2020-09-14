<?php
/**
 *
 *
 * @author clemmtl
 * @version $Id: prr_class.php 1582 2014-05-07 13:37:45Z clemmtl $
 *
 */

require_once( GSR_DOCUMENT_ROOT .  '/gsr/common/class/gsr_utilities.php');

class serv_perf
{ /*
  * Database connection object.
  * @var object
  */
  protected $db_obj;

  /*
  * Org class object.
  * @var object
  */
  protected $org_obj;

  /*
  * PL class object.
  * @var object
  */
  protected $pl_obj;
  protected $sg_obj;

  /**
  * Returned data from sql run formated into array
  *
  * @var array
  */
  public $data;

  /**
  * Business segments object.
  *
  * @var business_segments_select_class
  */
  public $bs_obj;

  protected $di_obj;


  // constructor
  function __construct($db_obj,$org_obj, $pl_obj,$sg_obj, $var_array, $bs_obj, $di_obj, $run_data=true) //, $di_obj) { // BEGIN constructor
  {
    $this->gsr_util = new gsr_utilities();
    $this->db_obj  = $db_obj;
    $this->org_obj = $org_obj;
    $this->pl_obj = $pl_obj;
    $this->sg_obj = $sg_obj;
    $this->bs_obj = $bs_obj;
    $this->di_obj = $di_obj;
  
    $this->REQUEST = $var_array;
    // Set Org description
    $this->org_desc  = $this->org_obj->get_org_desc();
    // set PL
    $this->_set_pl();
    // set ORG
    $this->_set_org();
    // set Periods
    $this->_set_timeslice();
  
    $this->_set_servicing_groups();
    $this->_set_business_segment();
  
    $this->_set_ticket_types();
    $this->_set_ticket_status();
    $this->_set_no_parts_used();
    $this->_set_handledby_name_array();
    $this->_set_center_name_array();
    if($this->REQUEST['sub_type'] == 'ind') {
      $this->_set_ticket_handledby();
    } else {
      $this->where_handledby = '';
    }
  
    if($this->REQUEST['sub_type'] == 'cntr') {
      $this->_set_center();
    } else {
      $this->where_center = ' ';
    }
  
    // Saving this here in case I need it.
    // select GSR_OPTION_VALUE as role
    // from gsr_admin.dbo.gsr_option_config
    // where gsr_option_group = 'CALL_CENTER_REPORT'
    // and gsr_option_name = 'CALL_CENTER_ROLES'
    // and comment = '1'
  
    // If we are doing the PRR by Experience Codes we need to
    // call some methods to configure the SQL so the data will have the right
    // elements
    if($this->REQUEST['report_type'] == 'prr_ex') {
      if($this->REQUEST['sub_type'] == 'ind') {
	    $this->select = "SELECT S.csc_handledby as entity, s.exp_code_2char + ': ' + s.exp_code_description_2_char as id,
	    s.exp_code_2char, s.exp_code_description_2_char, s.country, ";
	    $this->groupby = "GROUP BY S.csc_handledby, s.exp_code_2char, s.exp_code_description_2_char, s.country ";
	    $this->orderby = "ORDER BY 6 DESC";
	    $this->select2 = "SELECT ticket_priority,";
	    $this->groupby2 = "GROUP BY ticket_priority ";
	    $this->extra_join = " ";
      } elseif($this->REQUEST['sub_type'] == 'cntr') {
        $this->select = "SELECT c.center_id as entity, s.exp_code_2char + ': ' + s.exp_code_description_2_char as id,
        s.exp_code_2char, s.exp_code_description_2_char, s.country, ";
        $this->groupby = "GROUP BY c.Center_id, s.exp_code_2char, s.exp_code_description_2_char, s.country ";
        $this->orderby = "ORDER BY 6 DESC";
        $this->select2 = "SELECT ticket_priority,";
        $this->groupby2 = "GROUP BY ticket_priority ";
        $this->extra_join = " inner join GSR_ADMIN.dbo.GSR_ADMIN_REF_CENTERS c on
                                            l.center_id = c.center_id ";
      } else {
        $this->select = "SELECT s.exp_code_2char + ': ' + s.exp_code_description_2_char as id,
        s.exp_code_2char, s.exp_code_description_2_char, s.country as entity, s.country, ";
        $this->groupby = "GROUP BY s.exp_code_2char, s.exp_code_description_2_char, s.country ";
        $this->orderby = "ORDER BY 5 DESC";
        $this->select2 = "SELECT ticket_priority,";
        $this->groupby2 = "GROUP BY ticket_priority ";
        $this->extra_join = " ";
      }
       
    }  else {
      switch($this->REQUEST['sub_type']) {
        case "ind":
          $this->select = "SELECT S.csc_handledby as entity, S.country, S.pl, ";
          $this->groupby = "GROUP BY S.csc_handledby, S.receipt_period, S.country, S.pl ";
          $this->orderby = "ORDER BY 4 ";
          $this->extra_join = " ";
          break;;
        case "cntr":
          $this->select = "SELECT c.Center_Id as entity, S.country, S.pl, ";
          $this->groupby = "GROUP BY c.Center_id, S.country, S.receipt_period, S.pl ";
          $this->orderby = "ORDER BY 4 ";
          $this->extra_join = " inner join GSR_ADMIN.dbo.GSR_ADMIN_REF_CENTERS c on
                                  l.center_id = c.center_id ";
  
          break;
        case "ctry":
          $this->select = "SELECT S.country as entity, S.pl, S.country, ";
          $this->groupby = "GROUP BY S.receipt_period, S.country, S.pl ";
          $this->orderby = "ORDER BY 3 ";
          $this->extra_join = " ";
  
          break;
        }
      }

    $this->data_where = "";
    $this->meas_where = "";

  
    $this->return_array_items = array("PERIOD", "value");
  
    //$this->period_array = $this->fillPeriodArray($this->REQUEST['di_min'],$this->REQUEST['di_max']);
    $this->period_array = $this->fillPeriodArray($this->REQUEST['di_min'],$this->REQUEST['di_max']);
  
    // This will create the $this->final_where variable
    $this->_buildWhere();
    if($run_data){
      // Build the SQL needed to pull the data for the selections made
      $this->_build_sql();
      // Run SQL
      $this->_runSQL();
  
      // Build Data array
      if($this->_set_report_data()){
       // Smooth report data
       $this->class_success = true;
      } else {
       $this->class_success = false;
      };
    }
  } // END constructor
   
  
  private function add_months_to_period($period_in, $months_to_shift_in) {
    // Use - (negative) months to shift backwards.
    if (is_null($period_in) OR is_null($months_to_shift_in)) { return NULL; }
    $year = substr($period_in, 0, 4);
    $month = substr($period_in, 4, 2);
    $new_period = date('Ym',mktime(1, 1, 1, ($month + $months_to_shift_in ), 1, $year));
    return $new_period;
  }  // End function add_months_to_period
  
  
  public function fillPeriodArray($start_period, $end_period){
    // Check to make sure start is before end
    if($start_period > $end_period){
      echo "Error: Start period is equal to or later than end period.";
      exit;
    }
  
    $start_year = left($start_period,4);
    $start_month = right($start_period,2);
    $end_year = left($end_period,4);
    $end_month = right($end_period,2);
  
    while($start_period<=$end_period){
      $period_array[] = $start_period;
      if(right($start_period,2) == 12){
        // increment year
        $start_year++;
        $start_period =  $start_year . "01";
      } else {
        $start_period =  $start_period +1;
      }
    }
    return $period_array;
  }
  
  public function get_data(){
    $this->data['login_mapping'] = $this->login_mapping_array;
    $this->data['center_mapping'] = $this->center_mapping_array;
    if($this->REQUEST['report_type'] == 'prr_ex') {
      if ( isset($this->total_exp_code_data) ) {
        if($this->REQUEST['sub_type'] == 'ind') {
          $this->data['total'] = $this->total_exp_code_data;
          
          $this->data['entity'] = $this->exp_code_data;
          $this->data['entity_country'] = $this->exp_code_data_country;
          
          $this->data['up_down'] = $this->up_down_data;
          $this->data['codes_exp_code'] = $this->codes_exp_code_data;
        } elseif($this->REQUEST['sub_type'] == 'cntr') {
          $this->data['total'] = $this->total_exp_code_data;
          $this->data['total_centers'] = $this->centers_total_exp_code_data;
          $this->data['entity'] = $this->exp_code_data;
          $this->data['entity_country'] = $this->exp_code_data_country;
          
          $this->data['up_down'] = $this->up_down_data;
          $this->data['codes_exp_code'] = $this->codes_exp_code_data;
        } else {
          $this->data['total'] = $this->total_exp_code_data;
          $this->data['entity'] = $this->exp_code_data;
          $this->data['entity_country'] = $this->exp_code_data_country;
          
          $this->data['up_down'] = $this->up_down_data;
          $this->data['codes_exp_code'] = $this->codes_exp_code_data;
        }
      }
    } else {
      if ( isset($this->total) ) {
        $this->data['total'] = $this->total;
        $this->data['total_pl'] = $this->total_pl;
        $this->data['total_period'] = $this->total_period;
        $this->data['total_pl_period'] = $this->total_pl_period;
        
        $this->data['entity'] = $this->entity;
        $this->data['entity_country'] = $this->entity_country;
        $this->data['entity_pl'] = $this->entity_pl;
        $this->data['entity_period'] = $this->entity_period;
        $this->data['entity_pl_period'] = $this->entity_pl_period;
      }
    }
  
    return json_encode($this->data);
  }
  
  public function _set_goal_area(){
    // Lets figure out the parent area so that we can display goal by area if needed.
    $org_where2 = $this->org_obj->get_org_sql("country");
    // Do we have anything.  If not it must be WorldWide
    if($org_where2 ==""){
      $this->area_goal_avail = true;
      $this->goal_area = "WorldWide";
    } else {
      // Lets split into array
      $org_where_array = preg_split('/AND/',$org_where2);
      if(count($org_where_array) >1){
        // Check element [1] to see if we need an ending ')' added
        $open_paren = substr_count($org_where_array[1],'(');
        $close_paren = substr_count($org_where_array[1],')');
        if ($open_paren > $close_paren) {
            $org_where2 = "AND " . $org_where_array[1] . ")";
        } else {
            $org_where2 = "AND " . $org_where_array[1];
        }
      } else {
        $org_where2 = "";
      }
      $SQL = "SELECT DISTINCT GAO.org_name
              FROM GSR_ADMIN.dbo.GSR_ADMIN_ORG_COUNTRIES GAOC,
                   GSR_ADMIN.dbo.GSR_ADMIN_ORGS GAO
              WHERE GAOC.org_name = GAO.org_name AND GAO.org_prefix_id = 2
                    $org_where2 ";
  
      // run sql using database object
      $rs = $this->db_obj->run_sql_with_exit_on_error($SQL);
  
      while ($row = $this->db_obj->fetch_row_array_num($rs)) {
        // Create an array
        $parent_area[] = $row[0];
      }
      if(count($parent_area) == 1){
        $this->area_goal_avail = true;
        $this->goal_area = $parent_area[0];
        //echo "Goal Area = $this->goal_area<br>";
      } else {
        $this->goal_area = "WorldWide";
        $this->area_goal_avail = false;
      }
     }
  }
  
  /**
    * Creates the SQL for the Handled By
    *
    *
    */
  
  protected function _set_ticket_handledby(){
    // Try some individual stuff here
    $selected_handledby_array = (isset($this->REQUEST['ind_hand'])) ?$this->REQUEST['ind_hand']: array();
    $this->selected_handled_by_logins = array();
    if(is_array($selected_handledby_array)){
      // Is this array empty
      if(count($selected_handledby_array) == 0){
        //echo "Selected is Empty, Nothing to do here<br>";
        $this->where_handledby = ' ';
      } else {
        $this->where_handledby = " and l.login IN ( '---'";
        foreach($selected_handledby_array as $ind_hand_data){
          $person_data =  explode('::',$ind_hand_data);
          $person = $person_data[0];
          $this->selected_handled_by_logins[] = $person_data[0];
          $person_name = $person_data[1];
          $this->where_handledby .= ",'$person'";
        }
        $this->where_handledby .= " )";
      }
    } else {
     //$ind_hand_js_array = "var parm_cust_array = new Array();\n";
    }
  }
  
  protected function _set_handledby_name_array(){
  
    $sql = "
    SELECT DISTINCT  l.login
       ,coalesce(r.PublishableNameOverride,FullName) as name
    FROM GSR_ADMIN.dbo.GSR_ADMIN_REF_LOGIN_CENTER l
    left join [GSR_ADMIN].[dbo].[GSR_ADMIN_REF_LOGIN] r ON
         l.login = r.LOGIN
    ORDER BY
         coalesce(r.PublishableNameOverride,FullName)
    ";
  
    $rs = $this->db_obj->run_sql_with_exit_on_error($sql);
  
    $result_array = $this->db_obj->fetch_array_assoc($rs);
    $this->login_mapping_array = array();
    foreach($result_array as $row){
      $this->login_mapping_array[$row['login']] = $row['name'];
    }
  
  }
  
  protected function _set_center_name_array(){
  
    $sql = "
      SELECT DISTINCT  l.Center_Id as center_id
         ,l.center_name as name
      FROM GSR_ADMIN.dbo.GSR_ADMIN_REF_CENTERS l
      Where isActive = 1
      ORDER BY l.center_name
    ";
  
    $rs = $this->db_obj->run_sql_with_exit_on_error($sql);
  
    $result_array = $this->db_obj->fetch_array_assoc($rs);
    $this->center_mapping_array = array();
    foreach($result_array as $row){
      $this->center_mapping_array[$row['center_id']] = $row['name'];
    }
  
  }
  
  protected function _set_center(){
  
    $selected_center_array = (isset($this->REQUEST['centers'])) ?$this->REQUEST['centers']: array();
    if(is_array($selected_center_array)){
      // Is this array empty
      if(count($selected_center_array) == 0){
        //echo "Selected is Empty, Nothing to do here<br>";
        $this->where_center = ' ';
      } else {
        $this->where_center = " and l.Center_id IN ( 0";
        foreach($selected_center_array as $_center_data){
  
         $center_data =  explode('::',$_center_data);
         $center_id = $center_data[0];
         $center_name = $center_data[1];
         $this->where_center .= ",$center_id";
        }
        $this->where_center .= " )";
      }
    } else {
      //$ind_hand_js_array = "var parm_cust_array = new Array();\n";
    }
  
  }
  
  
  /**
    * Creates the SQL for the PL IN Clause
    *
    * Uses the passed in $_REQUEST parameters to determine the PLs selected in the viewer.
    * This is called by the constructor.
    *
    */
  
  protected function _set_pl(){
  
    // Initial pl_where start
    $this->pl_where = $this->pl_obj->get_pl_sql(" AND S.pl");
    $this->goal_pl_where = $this->pl_obj->get_pl_sql(" AND field1");
    $pl_selected_array=$this->pl_obj->get_selected_pls();
    //pr($pl_selected_array);
    $this->pl_count = count($pl_selected_array);
    $this->pl_count = 0;
  
  }
  
  /**
    * Creates the SQL for the country IN Clause
    *
    * Uses the passed in org object to generate the sql where clause.
    * This is called by the constructor.
    *
    */
  protected function _set_org(){
  
    // Get the Where sql for the ORG selections
    $this->org_where = $this->org_obj->get_org_sql('S.country','S.locallevel1','S.locallevel2');
    //echo "Org Where: ".  $this->org_where ."<br>";
  
  }
  
  
  /**
    * Creates the SQL for the period where statement
    *
    * Uses the passed in $_REQUEST to generate the sql where clause.
    * This is called by the constructor.
    *
    */
  protected function _set_timeslice(){
  
    $di_int = (isset($this->REQUEST['di_int'])) ? $this->REQUEST['di_int'] : 'm';
    switch ($di_int) {
      case 'y':
        $this->select_date_interval = " s.receipt_year as period, ";
        $this->groupby_date_interval = " s.receipt_year ";
        $start_year = $this->REQUEST['di_min'];
        $end_year  = $this->REQUEST['di_max'];
        $this->timeslice_where = " and s.receipt_year   between ${start_year} and ${end_year} ";
  
        break;
      case 'q':
        $this->select_date_interval = " cast(s.receipt_year as varchar)+ 'Q' + cast(s.receipt_quarter as varchar) as period, ";
        $this->groupby_date_interval = " cast(s.receipt_year as varchar)+ 'Q' + cast(s.receipt_quarter as varchar) ";
        // Split out the start and end items
        $start_year = left($this->REQUEST['di_min'],4 );
        $start_qtr  = right($this->REQUEST['di_min'],1 );
  
        $end_year = left($this->REQUEST['di_max'],4 );
        $end_qtr  = right($this->REQUEST['di_max'],1 );
  
        $this->timeslice_where = " and cast(s.receipt_year as varchar) + cast(s.receipt_quarter as varchar)  between ${start_year}${start_qtr} and ${end_year}${end_qtr} ";
  
        break;
      case 'm':
        $this->select_date_interval = " S.receipt_period as period, ";
        $this->groupby_date_interval = " S.receipt_period ";
  
        $this->timeslice_where = " AND S.receipt_period between ". $this->REQUEST['di_min'] ." AND ".$this->REQUEST['di_max']." ";
  
        break;
      case 'w':
        $this->select_date_interval = " cast(s.receipt_year as varchar) + 'W' + RIGHT('00'+CAST(s.receipt_week AS VARCHAR(2)),2) as period, ";
        $this->groupby_date_interval = " cast(s.receipt_year as varchar) + 'W' + RIGHT('00'+CAST(s.receipt_week AS VARCHAR(2)),2) ";
  
        // Split out the start and end items
        $start_year = left($this->REQUEST['di_min'],4 );
        $start_week  = right($this->REQUEST['di_min'],2 );
  
        $end_year = left($this->REQUEST['di_max'],4 );
        $end_week  = right($this->REQUEST['di_max'],2 );
  
        $this->timeslice_where = " and cast(s.receipt_year as varchar) + RIGHT('00'+CAST(s.receipt_week AS VARCHAR(2)),2)  between ${start_year}${start_week} and ${end_year}${end_week} ";
  
        break;
    }
	
    // Get the Where sql for the ORG selections
    // period
    $this->period_where = " AND S.receipt_period between ". $this->REQUEST['di_min'] ." AND ".$this->REQUEST['di_max']." ";
    $this->chart_period_array = $this->fillPeriodArray($this->REQUEST['di_min'],$this->REQUEST['di_max']);
    $this->num_mo_disp = count($this->chart_period_array);
  
    // Check if max period is the current partial month.
    $this->current_period = date('Ym');
    if($this->REQUEST['di_max'] == $this->current_period){
      $this->partial_period=true;
    } else {
      $this->partial_period=false;
    }
  
  }
  
  
  /**
    * Creates the SQL for the ticket types
    *
    */
  protected function _set_ticket_types(){
    $comp = (isset($this->REQUEST['comp'])) ? $this->REQUEST['comp'] : 0;
    $sd = (isset($this->REQUEST['sd'])) ? $this->REQUEST['sd'] : 0;
    $sp = (isset($this->REQUEST['sp'])) ? $this->REQUEST['sp'] : 0;
    $inq = (isset($this->REQUEST['inq'])) ? $this->REQUEST['inq'] : 0;
  
    if($comp OR $sd OR $sp OR $inq ) {
      $this->ticket_type_where = " and s.ticket_type_full IN ('---'";
      $this->ticket_type_where .= ($comp) ?  ",'c'": '';
      $this->ticket_type_where .= ($sd) ?  ",'sd'": '';
      $this->ticket_type_where .= ($sp) ?  ",'sp'": '';
      $this->ticket_type_where .= ($inq) ?  ",'i'": '';
      $this->ticket_type_where .= ") ";
      // pr($this->ticket_type_where);
    }  else {
      echo "Must make a ticket type selection";
      exit;
    }
  
  }
  
  protected function _set_no_parts_used () {
    $this->no_parts_used_where = " ";
    if(isset($this->REQUEST['no_parts'])){
     $this->no_parts_used_where = " and s.parts_replaced_flag = 0 ";
    }
  
  }
  
  
  /**
    * Creates the SQL for the ticket types
    *
    */
  protected function _set_ticket_status(){
    $t_clsd = (isset($this->REQUEST['t_clsd'])) ? $this->REQUEST['t_clsd'] : 0;
    $t_open = (isset($this->REQUEST['t_open'])) ? $this->REQUEST['t_open'] : 0;
    $t_void = (isset($this->REQUEST['t_void'])) ? $this->REQUEST['t_void'] : 0;
  
    if($t_clsd OR $t_open OR $t_void ) {
      $this->ticket_status_where = " and s.ticket_status IN ('---'";
      $this->ticket_status_where .= ($t_clsd) ?  ",'C'": '';
      $this->ticket_status_where .= ($t_open) ?  ",'O'": '';
      $this->ticket_status_where .= ($t_void) ?  ",'V'": '';
      $this->ticket_status_where .= ") ";
      //pr($this->ticket_status_where);
    }  else {
      echo "Must make Ticket Status selection";
      exit;
    }
  
  }
  
  
  public function _set_data_elements($field, $values){
     // check if parameters is valid array
     if(is_array($values)){
       $this->data_where .= " AND $field IN (";
       foreach($values as $item){
         $this->data_where .= "'$item',";
       }
       // trim off trailing comma and close off the 'IN' clause
       $this->data_where = substr($this->data_where,0,-1) . ")";
     } else {
       echo '<span style="color: red">ERROR: Requires values array.</span>';
       exit;
     }
  
  }
  
  
  protected function _buildWhere(){
  
    $this->final_where = "WHERE 1=1 \r\n and coalesce(s.predictive_system, '---') <> 'DAVINCISVC' \r\n";
  
    $this->final_where .= $this->pl_where ."\r\n";
    $this->final_where .= $this->org_where ."\r\n";
    $this->final_where .= $this->timeslice_where ."\r\n";
    $this->final_where .= $this->data_where ."\r\n";
    $this->final_where .= $this->meas_where ."\r\n";
    $this->final_where .= $this->where_bs ."\r\n";
    $this->final_where .= $this->where_sg ."\r\n";
    $this->final_where .= $this->ticket_type_where ."\r\n";
    $this->final_where .= $this->ticket_status_where ."\r\n";
    $this->final_where .= $this->no_parts_used_where ."\r\n";
    $this->final_where .= $this->where_handledby ."\r\n";
    $this->final_where .= $this->where_center ."\r\n";
  
    // Ticket Opended by role
    $where_opened_by_role_sys = " and S.ticket_openedby_role_sys IN ('---'";
    if(isset($this->REQUEST['by_oper'])){
      $where_opened_by_role_sys .= ", 'OPERATOR'";
    }
    if(isset($this->REQUEST['by_phm'])){
      $where_opened_by_role_sys .= ", 'phm',  'pom'";
    }
    if(isset($this->REQUEST['by_tso'])){
      $where_opened_by_role_sys .= ", 'tso'";
    }
    $where_opened_by_role_sys .= ") ";
    $this->final_where .= $where_opened_by_role_sys ."\r\n";

    // Do not include voided tickets   $this->where_handledby
    //$this->final_where .=  " AND TC.STATUS NOT IN ('V') ";
    //$this->final_where .=  " AND (SC.SeasReportingCategoryID = 1 OR SC.SeasReportingCategoryID IS NULL) ";
  
  }
  
  
  protected function _runSQL(){
  
    $this->rs = $this->db_obj->run_sql_with_exit_on_error($this->SQL);
    if($this->REQUEST['report_type'] == 'prr_ex') {
       $this->rs2 = $this->db_obj->run_sql_with_exit_on_error($this->SQL2);
    }
  
  }
  
  protected function _set_servicing_groups(){
  
      $this->where_sg = $this->sg_obj->get_sg_sql("S.calc_servicing_group_site","S.calc_servicing_group_site");
      $this->census_where_sg = $this->sg_obj->get_sg_sql("calc_servicing_group_site","calc_servicing_group_site");
  }
  
  protected function _set_business_segment(){
  
      $this->where_bs = $this->bs_obj->get_bs_pl_bs_sql("S.calc_business_segment","PL.pl_business_segment");
  }
  
  protected function _set_report_data(){
  
    $result_array = $this->db_obj->fetch_array_assoc($this->rs);
  
    if(!$this->db_obj->sqlsrv_has_rows($this->rs)){
      return false;
    } else {
         if($this->REQUEST['report_type'] == 'prr_ex') {
            if($this->REQUEST['sub_type'] == 'ind') {

               // Do the up/down data
               $result_array2 = $this->db_obj->fetch_array_assoc($this->rs2);
               foreach($result_array2 as $row2){
                 $this->up_down_data[$row2['ticket_priority']]['phone_fix'] =  $row2['phone_fix_count'];
                 $this->up_down_data[$row2['ticket_priority']]['site_visit'] =  $row2['site_visit_count'];
                 $this->up_down_data[$row2['ticket_priority']]['total_calls'] =  $row2['total_calls'];
               }
  
               // Walk the result array and format into chart data array
               $this->total_exp_code_data = array();
               $this->total_exp_code_data['phone_fix'] = 0;
               $this->total_exp_code_data['site_visit'] = 0;
               $this->total_exp_code_data['total_calls'] = 0;
               $this->total_exp_code_data['km_used'] = 0;
               $this->total_exp_code_data['al_used'] = 0;
               $this->total_exp_code_data['proa'] = 0;
               $this->total_exp_code_data['proa_pf'] = 0;
  
               $this->codes_exp_code_data = array();
               $this->exp_code_data = array();
               $this->exp_code_data['phone_fix'] = array();
               $this->exp_code_data['site_visit'] = array();
               $this->exp_code_data['total_calls'] = array();
               $this->exp_code_data['km_used'] = array();
               $this->exp_code_data['al_used'] = array();
               $this->exp_code_data['proa'] = array();
               $this->exp_code_data['proa_pf'] = array();
               $this->exp_code_data_country = array();
               $this->exp_code_data_country['phone_fix'] = array();
               $this->exp_code_data_country['site_visit'] = array();
               $this->exp_code_data_country['total_calls'] = array();
               $this->exp_code_data_country['km_used'] = array();
               $this->exp_code_data_country['al_used'] = array();
               $this->exp_code_data_country['proa'] = array();
               $this->exp_code_data_country['proa_pf'] = array();
  
               foreach($result_array as $row){
                 // Codes
                 if(!isset($this->codes_exp_code_data[$row['id']])){
                   $this->codes_exp_code_data[$row['id']]['exp_code_2char'] = $row['exp_code_2char'];
                   $this->codes_exp_code_data[$row['id']]['exp_code_description_2_char'] = $row['exp_code_description_2_char'];
                 }
    
                 // Total
                 if(isset($this->total_exp_code_data['phone_fix'])){
                   $this->total_exp_code_data['phone_fix'] += $row['phone_fix_count'];
                 } else {
                   $this->total_exp_code_data['phone_fix'] = $row['phone_fix_count'];
                 }
                 if(isset($this->total_exp_code_data['site_visit'])){
                   $this->total_exp_code_data['site_visit'] += $row['site_visit_count'];
                 } else {
                   $this->total_exp_code_data['site_visit'] = $row['site_visit_count'];
                 }
                 if(isset($this->total_exp_code_data['total_calls'])){
                   $this->total_exp_code_data['total_calls'] += $row['total_calls'];
                 } else {
                   $this->total_exp_code_data['total_calls'] = $row['total_calls'];
                 }
                 if(isset($this->total_exp_code_data['km_used'])){
                   $this->total_exp_code_data['km_used'] += $row['km_used_count'];
                 } else {
                   $this->total_exp_code_data['km_used'] = $row['km_used_count'];
                 }
                 if(isset($this->total_exp_code_data['al_used'])){
                   $this->total_exp_code_data['al_used'] += $row['al_used_count'];
                 } else {
                   $this->total_exp_code_data['al_used'] = $row['al_used_count'];
                 }
                 if(isset($this->total_exp_code_data['proa'])){
                   $this->total_exp_code_data['proa'] += $row['proa_total'];
                 } else {
                   $this->total_exp_code_data['proa'] = $row['proa_total'];
                 }
                 if(isset($this->total_exp_code_data['proa_pf'])){
                   $this->total_exp_code_data['proa_pf'] += $row['proa_count'];
                 } else {
                   $this->total_exp_code_data['proa_pf'] = $row['proa_count'];
                 }
    
                 // Error
                 if(isset($this->exp_code_data['phone_fix'][$row['id']])){
                   $this->exp_code_data['phone_fix'][$row['id']] += $row['phone_fix_count'];
                 } else {
                   $this->exp_code_data['phone_fix'][$row['id']] = $row['phone_fix_count'];
                 }
                 if(isset($this->exp_code_data['site_visit'][$row['id']])){
                   $this->exp_code_data['site_visit'][$row['id']] += $row['site_visit_count'];
                 } else {
                   $this->exp_code_data['site_visit'][$row['id']] = $row['site_visit_count'];
                 }
                 if(isset($this->exp_code_data['total_calls'][$row['id']])){
                   $this->exp_code_data['total_calls'][$row['id']] += $row['total_calls'];
                 } else {
                   $this->exp_code_data['total_calls'][$row['id']] = $row['total_calls'];
                 }
                 if(isset($this->exp_code_data['km_used'][$row['id']])){
                   $this->exp_code_data['km_used'][$row['id']] += $row['km_used_count'];
                 } else {
                   $this->exp_code_data['km_used'][$row['id']] = $row['km_used_count'];
                 }
                 if(isset($this->exp_code_data['al_used'][$row['id']])){
                   $this->exp_code_data['al_used'][$row['id']] += $row['al_used_count'];
                 } else {
                   $this->exp_code_data['al_used'][$row['id']] = $row['al_used_count'];
                 }
                 if(isset($this->exp_code_data['proa'][$row['id']])){
                   $this->exp_code_data['proa'][$row['id']] += $row['proa_total'];
                 } else {
                   $this->exp_code_data['proa'][$row['id']] = $row['proa_total'];
                 }
                 if(isset($this->exp_code_data['proa_pf'][$row['id']])){
                   $this->exp_code_data['proa_pf'][$row['id']] += $row['proa_count'];
                 } else {
                   $this->exp_code_data['proa_pf'][$row['id']] = $row['proa_count'];
                 }
    
                 // error Country
                 if(isset($this->exp_code_data_country['phone_fix'][$row['id']][$row['entity']])){
                   $this->exp_code_data_country['phone_fix'][$row['id']][$row['entity']] += $row['phone_fix_count'];
                 } else {
                   $this->exp_code_data_country['phone_fix'][$row['id']][$row['entity']] = $row['phone_fix_count'];
                 }
                 if(isset($this->exp_code_data_country['site_visit'][$row['id']][$row['entity']])){
                   $this->exp_code_data_country['site_visit'][$row['id']][$row['entity']] += $row['site_visit_count'];
                 } else {
                   $this->exp_code_data_country['site_visit'][$row['id']][$row['entity']] = $row['site_visit_count'];
                 }
                 if(isset($this->exp_code_data_country['total_calls'][$row['id']][$row['entity']])){
                   $this->exp_code_data_country['total_calls'][$row['id']][$row['entity']] += $row['total_calls'];
                 } else {
                   $this->exp_code_data_country['total_calls'][$row['id']][$row['entity']] = $row['total_calls'];
                 }
                 if(isset($this->exp_code_data_country['km_used'][$row['id']][$row['entity']])){
                   $this->exp_code_data_country['km_used'][$row['id']][$row['entity']] += $row['km_used_count'];
                 } else {
                   $this->exp_code_data_country['km_used'][$row['id']][$row['entity']] = $row['km_used_count'];
                 }
                 if(isset($this->exp_code_data_country['al_used'][$row['id']][$row['entity']])){
                   $this->exp_code_data_country['al_used'][$row['id']][$row['entity']] += $row['al_used_count'];
                 } else {
                   $this->exp_code_data_country['al_used'][$row['id']][$row['entity']] = $row['al_used_count'];
                 }
                 if(isset($this->exp_code_data_country['proa'][$row['id']][$row['entity']])){
                   $this->exp_code_data_country['proa'][$row['id']][$row['entity']] += $row['proa_total'];
                 } else {
                   $this->exp_code_data_country['proa'][$row['id']][$row['entity']] = $row['proa_total'];
                 }
                 if(isset($this->exp_code_data_country['proa_pf'][$row['id']][$row['entity']])){
                   $this->exp_code_data_country['proa_pf'][$row['id']][$row['entity']] += $row['proa_count'];
                 } else {
                   $this->exp_code_data_country['proa_pf'][$row['id']][$row['entity']] = $row['proa_count'];
                 }
               }
             } elseif($this->REQUEST['sub_type'] == 'cntr') {

                // Do the up/down data
                $result_array2 = $this->db_obj->fetch_array_assoc($this->rs2);
                foreach($result_array2 as $row2){
                  $this->up_down_data[$row2['ticket_priority']]['phone_fix'] =  $row2['phone_fix_count'];
                  $this->up_down_data[$row2['ticket_priority']]['site_visit'] =  $row2['site_visit_count'];
                  $this->up_down_data[$row2['ticket_priority']]['total_calls'] =  $row2['total_calls'];
  
                }
  
                // Walk the result array and format into chart data array
                $this->total_exp_code_data = array();
                $this->total_exp_code_data['phone_fix'] = 0;
                $this->total_exp_code_data['site_visit'] = 0;
                $this->total_exp_code_data['total_calls'] = 0;
                $this->total_exp_code_data['km_used'] = 0;
                $this->total_exp_code_data['al_used'] = 0;
                $this->total_exp_code_data['proa'] = 0;
                $this->total_exp_code_data['proa_pf'] = 0;
  
                $this->codes_exp_code_data = array();
                $this->exp_code_data = array();
                $this->centers_total_exp_code_data = array();
  
                $this->exp_code_data['phone_fix'] = array();
                $this->exp_code_data['site_visit'] = array();
                $this->exp_code_data['total_calls'] = array();
                $this->exp_code_data['km_used'] = array();
                $this->exp_code_data['al_used'] = array();
                $this->exp_code_data['proa'] = array();
                $this->exp_code_data['proa_pf'] = array();
  
                $this->centers_total_exp_code_data['phone_fix'] = array();
                $this->centers_total_exp_code_data['site_visit'] = array();
                $this->centers_total_exp_code_data['total_calls'] = array();
                $this->centers_total_exp_code_data['km_used'] = array();
                $this->centers_total_exp_code_data['al_used'] = array();
                $this->centers_total_exp_code_data['proa'] = array();
                $this->centers_total_exp_code_data['proa_pf'] = array();
  
                $this->exp_code_data_country = array();
                $this->exp_code_data_country['phone_fix'] = array();
                $this->exp_code_data_country['site_visit'] = array();
                $this->exp_code_data_country['total_calls'] = array();
                $this->exp_code_data_country['km_used'] = array();
                $this->exp_code_data_country['al_used'] = array();
                $this->exp_code_data_country['proa'] = array();
                $this->exp_code_data_country['proa_pf'] = array();
  
                foreach($result_array as $row){
                
				// Codes
                if(!isset($this->codes_exp_code_data[$row['id']])){
                  $this->codes_exp_code_data[$row['id']]['exp_code_2char'] = $row['exp_code_2char'];
                  $this->codes_exp_code_data[$row['id']]['exp_code_description_2_char'] = $row['exp_code_description_2_char'];
                }
  
                // Total
                if(isset($this->total_exp_code_data['phone_fix'])){
                  $this->total_exp_code_data['phone_fix'] += $row['phone_fix_count'];
                } else {
                  $this->total_exp_code_data['phone_fix'] = $row['phone_fix_count'];
                }
                if(isset($this->total_exp_code_data['site_visit'])){
                  $this->total_exp_code_data['site_visit'] += $row['site_visit_count'];
                } else {
                  $this->total_exp_code_data['site_visit'] = $row['site_visit_count'];
                }
                if(isset($this->total_exp_code_data['total_calls'])){
                  $this->total_exp_code_data['total_calls'] += $row['total_calls'];
                } else {
                  $this->total_exp_code_data['total_calls'] = $row['total_calls'];
                }
                if(isset($this->total_exp_code_data['km_used'])){
                  $this->total_exp_code_data['km_used'] += $row['km_used_count'];
                } else {
                  $this->total_exp_code_data['km_used'] = $row['km_used_count'];
                }
                if(isset($this->total_exp_code_data['al_used'])){
                  $this->total_exp_code_data['al_used'] += $row['al_used_count'];
                } else {
                  $this->total_exp_code_data['al_used'] = $row['al_used_count'];
                }
                if(isset($this->total_exp_code_data['proa'])){
                  $this->total_exp_code_data['proa'] += $row['proa_total'];
                } else {
                  $this->total_exp_code_data['proa'] = $row['proa_total'];
                }
                if(isset($this->total_exp_code_data['proa_pf'])){
                  $this->total_exp_code_data['proa_pf'] += $row['proa_count'];
                } else {
                  $this->total_exp_code_data['proa_pf'] = $row['proa_count'];
                }
  
                // Total Centers
                if(isset($this->centers_total_exp_code_data['phone_fix'][$row['entity']])){
                  $this->centers_total_exp_code_data['phone_fix'][$row['entity']] += $row['phone_fix_count'];
                } else {
                  $this->centers_total_exp_code_data['phone_fix'][$row['entity']] = $row['phone_fix_count'];
                }
                if(isset($this->centers_total_exp_code_data['site_visit'][$row['entity']])){
                  $this->centers_total_exp_code_data['site_visit'][$row['entity']] += $row['site_visit_count'];
                } else {
                  $this->centers_total_exp_code_data['site_visit'][$row['entity']] = $row['site_visit_count'];
                }
                if(isset($this->centers_total_exp_code_data['total_calls'][$row['entity']])){
                  $this->centers_total_exp_code_data['total_calls'][$row['entity']] += $row['total_calls'];
                } else {
                  $this->centers_total_exp_code_data['total_calls'][$row['entity']] = $row['total_calls'];
                }
                if(isset($this->centers_total_exp_code_data['km_used'][$row['entity']])){
                  $this->centers_total_exp_code_data['km_used'][$row['entity']] += $row['km_used_count'];
                } else {
                  $this->centers_total_exp_code_data['km_used'][$row['entity']] = $row['km_used_count'];
                }
                if(isset($this->centers_total_exp_code_data['al_used'][$row['entity']])){
                  $this->centers_total_exp_code_data['al_used'][$row['entity']] += $row['al_used_count'];
                } else {
                  $this->centers_total_exp_code_data['al_used'][$row['entity']] = $row['al_used_count'];
                }
                if(isset($this->centers_total_exp_code_data['proa'][$row['entity']])){
                  $this->centers_total_exp_code_data['proa'][$row['entity']] += $row['proa_total'];
                } else {
                    $this->centers_total_exp_code_data['proa'][$row['entity']] = $row['proa_total'];
                }
                if(isset($this->centers_total_exp_code_data['proa_pf'][$row['entity']])){
                    $this->centers_total_exp_code_data['proa_pf'][$row['entity']] += $row['proa_count'];
                } else {
                  $this->centers_total_exp_code_data['proa_pf'][$row['entity']] = $row['proa_count'];
                }
  
                // Error
                if(isset($this->exp_code_data['phone_fix'][$row['id']])){
                  $this->exp_code_data['phone_fix'][$row['id']] += $row['phone_fix_count'];
                } else {
                  $this->exp_code_data['phone_fix'][$row['id']] = $row['phone_fix_count'];
                }
                if(isset($this->exp_code_data['site_visit'][$row['id']])){
                  $this->exp_code_data['site_visit'][$row['id']] += $row['site_visit_count'];
                } else {
                  $this->exp_code_data['site_visit'][$row['id']] = $row['site_visit_count'];
                }
                if(isset($this->exp_code_data['total_calls'][$row['id']])){
                  $this->exp_code_data['total_calls'][$row['id']] += $row['total_calls'];
                } else {
                  $this->exp_code_data['total_calls'][$row['id']] = $row['total_calls'];
                }
                if(isset($this->exp_code_data['km_used'][$row['id']])){
                  $this->exp_code_data['km_used'][$row['id']] += $row['km_used_count'];
                } else {
                  $this->exp_code_data['km_used'][$row['id']] = $row['km_used_count'];
                }
                if(isset($this->exp_code_data['al_used'][$row['id']])){
                  $this->exp_code_data['al_used'][$row['id']] += $row['al_used_count'];
                } else {
                  $this->exp_code_data['al_used'][$row['id']] = $row['al_used_count'];
                }
                if(isset($this->exp_code_data['proa'][$row['id']])){
                  $this->exp_code_data['proa'][$row['id']] += $row['proa_total'];
                } else {
                  $this->exp_code_data['proa'][$row['id']] = $row['proa_total'];
                }
                if(isset($this->exp_code_data['proa_pf'][$row['id']])){
                  $this->exp_code_data['proa_pf'][$row['id']] += $row['proa_count'];
                } else {
                  $this->exp_code_data['proa_pf'][$row['id']] = $row['proa_count'];
                }
  
                // error Country
                if(isset($this->exp_code_data_country['phone_fix'][$row['id']][$row['entity']])){
                  $this->exp_code_data_country['phone_fix'][$row['id']][$row['entity']] += $row['phone_fix_count'];
                } else {
                  $this->exp_code_data_country['phone_fix'][$row['id']][$row['entity']] = $row['phone_fix_count'];
                }
                if(isset($this->exp_code_data_country['site_visit'][$row['id']][$row['entity']])){
                  $this->exp_code_data_country['site_visit'][$row['id']][$row['entity']] += $row['site_visit_count'];
                } else {
                  $this->exp_code_data_country['site_visit'][$row['id']][$row['entity']] = $row['site_visit_count'];
                }
                if(isset($this->exp_code_data_country['total_calls'][$row['id']][$row['entity']])){
                  $this->exp_code_data_country['total_calls'][$row['id']][$row['entity']] += $row['total_calls'];
                } else {
                  $this->exp_code_data_country['total_calls'][$row['id']][$row['entity']] = $row['total_calls'];
                }
                if(isset($this->exp_code_data_country['km_used'][$row['id']][$row['entity']])){
                  $this->exp_code_data_country['km_used'][$row['id']][$row['entity']] += $row['km_used_count'];
                } else {
                  $this->exp_code_data_country['km_used'][$row['id']][$row['entity']] = $row['km_used_count'];
                }
                if(isset($this->exp_code_data_country['al_used'][$row['id']][$row['entity']])){
                  $this->exp_code_data_country['al_used'][$row['id']][$row['entity']] += $row['al_used_count'];
                } else {
                  $this->exp_code_data_country['al_used'][$row['id']][$row['entity']] = $row['al_used_count'];
                }
                if(isset($this->exp_code_data_country['proa'][$row['id']][$row['entity']])){
                  $this->exp_code_data_country['proa'][$row['id']][$row['entity']] += $row['proa_total'];
                } else {
                  $this->exp_code_data_country['proa'][$row['id']][$row['entity']] = $row['proa_total'];
                }
                if(isset($this->exp_code_data_country['proa_pf'][$row['id']][$row['entity']])){
                  $this->exp_code_data_country['proa_pf'][$row['id']][$row['entity']] += $row['proa_count'];
                } else {
                  $this->exp_code_data_country['proa_pf'][$row['id']][$row['entity']] = $row['proa_count'];
                }
              }
            } else {

              // Do the up/down data
              $result_array2 = $this->db_obj->fetch_array_assoc($this->rs2);
              foreach($result_array2 as $row2){
                  $this->up_down_data[$row2['ticket_priority']]['phone_fix'] =  $row2['phone_fix_count'];
                  $this->up_down_data[$row2['ticket_priority']]['site_visit'] =  $row2['site_visit_count'];
                  $this->up_down_data[$row2['ticket_priority']]['total_calls'] =  $row2['total_calls'];
  
              }
  
              // Walk the result array and format into chart data array
              $this->total_exp_code_data = array();
              $this->total_exp_code_data['phone_fix'] = 0;
              $this->total_exp_code_data['site_visit'] = 0;
              $this->total_exp_code_data['total_calls'] = 0;
              $this->total_exp_code_data['km_used'] = 0;
              $this->total_exp_code_data['al_used'] = 0;
              $this->total_exp_code_data['proa'] = 0;
              $this->total_exp_code_data['proa_pf'] = 0;
  
              $this->codes_exp_code_data = array();
              $this->exp_code_data = array();
              $this->exp_code_data['phone_fix'] = array();
              $this->exp_code_data['site_visit'] = array();
              $this->exp_code_data['total_calls'] = array();
              $this->exp_code_data['km_used'] = array();
              $this->exp_code_data['al_used'] = array();
              $this->exp_code_data['proa'] = array();
              $this->exp_code_data['proa_pf'] = array();
              $this->exp_code_data_country = array();
              $this->exp_code_data_country['phone_fix'] = array();
              $this->exp_code_data_country['site_visit'] = array();
              $this->exp_code_data_country['total_calls'] = array();
              $this->exp_code_data_country['km_used'] = array();
              $this->exp_code_data_country['al_used'] = array();
              $this->exp_code_data_country['proa'] = array();
              $this->exp_code_data_country['proa_pf'] = array();
  
              foreach($result_array as $row){
                // Codes
                // Not all 2 character codes have the same description
  
                if(!isset($this->codes_exp_code_data[$row['id']])){
                  $this->codes_exp_code_data[$row['id']]['exp_code_2char'] = $row['exp_code_2char'];
                  $this->codes_exp_code_data[$row['id']]['exp_code_description_2_char'] = $row['exp_code_description_2_char'];
                }
  
                // Total
                if(isset($this->total_exp_code_data['phone_fix'])){
                  $this->total_exp_code_data['phone_fix'] += $row['phone_fix_count'];
                } else {
                  $this->total_exp_code_data['phone_fix'] = $row['phone_fix_count'];
                }
                if(isset($this->total_exp_code_data['site_visit'])){
                  $this->total_exp_code_data['site_visit'] += $row['site_visit_count'];
                } else {
                  $this->total_exp_code_data['site_visit'] = $row['site_visit_count'];
                }
                if(isset($this->total_exp_code_data['total_calls'])){
                  $this->total_exp_code_data['total_calls'] += $row['total_calls'];
                } else {
                  $this->total_exp_code_data['total_calls'] = $row['total_calls'];
                }
                if(isset($this->total_exp_code_data['km_used'])){
                  $this->total_exp_code_data['km_used'] += $row['km_used_count'];
                } else {
                  $this->total_exp_code_data['km_used'] = $row['km_used_count'];
                }
                if(isset($this->total_exp_code_data['al_used'])){
                  $this->total_exp_code_data['al_used'] += $row['al_used_count'];
                } else {
                  $this->total_exp_code_data['al_used'] = $row['al_used_count'];
                }
                if(isset($this->total_exp_code_data['proa'])){
                  $this->total_exp_code_data['proa'] += $row['proa_total'];
                } else {
                  $this->total_exp_code_data['proa'] = $row['proa_total'];
                }
                if(isset($this->total_exp_code_data['proa_pf'])){
                  $this->total_exp_code_data['proa_pf'] += $row['proa_count'];
                } else {
                  $this->total_exp_code_data['proa_pf'] = $row['proa_count'];
                }
  
                // Error
                if(isset($this->exp_code_data['phone_fix'][$row['id']])){
                  $this->exp_code_data['phone_fix'][$row['id']] += $row['phone_fix_count'];
                } else {
                  $this->exp_code_data['phone_fix'][$row['id']] = $row['phone_fix_count'];
                }
                if(isset($this->exp_code_data['site_visit'][$row['id']])){
                  $this->exp_code_data['site_visit'][$row['id']] += $row['site_visit_count'];
                } else {
                  $this->exp_code_data['site_visit'][$row['id']] = $row['site_visit_count'];
                }
                if(isset($this->exp_code_data['total_calls'][$row['id']])){
                  $this->exp_code_data['total_calls'][$row['id']] += $row['total_calls'];
                } else {
                  $this->exp_code_data['total_calls'][$row['id']] = $row['total_calls'];
                }
                if(isset($this->exp_code_data['km_used'][$row['id']])){
                  $this->exp_code_data['km_used'][$row['id']] += $row['km_used_count'];
                } else {
                  $this->exp_code_data['km_used'][$row['id']] = $row['km_used_count'];
                }
                if(isset($this->exp_code_data['al_used'][$row['id']])){
                  $this->exp_code_data['al_used'][$row['id']] += $row['al_used_count'];
                } else {
                  $this->exp_code_data['al_used'][$row['id']] = $row['al_used_count'];
                }
                if(isset($this->exp_code_data['proa'][$row['id']])){
                  $this->exp_code_data['proa'][$row['id']] += $row['proa_total'];
                } else {
                  $this->exp_code_data['proa'][$row['id']] = $row['proa_total'];
                }
                if(isset($this->exp_code_data['proa_pf'][$row['id']])){
                  $this->exp_code_data['proa_pf'][$row['id']] += $row['proa_count'];
                } else {
                  $this->exp_code_data['proa_pf'][$row['id']] = $row['proa_count'];
                }
  
                // error Country
                if(isset($this->exp_code_data_country['phone_fix'][$row['id']][$row['country']])){
                  $this->exp_code_data_country['phone_fix'][$row['id']][$row['country']] += $row['phone_fix_count'];
                } else {
                  $this->exp_code_data_country['phone_fix'][$row['id']][$row['country']] = $row['phone_fix_count'];
                }
                if(isset($this->exp_code_data_country['site_visit'][$row['id']][$row['country']])){
                  $this->exp_code_data_country['site_visit'][$row['id']][$row['country']] += $row['site_visit_count'];
                } else {
                  $this->exp_code_data_country['site_visit'][$row['id']][$row['country']] = $row['site_visit_count'];
                }
                if(isset($this->exp_code_data_country['total_calls'][$row['id']][$row['country']])){
                  $this->exp_code_data_country['total_calls'][$row['id']][$row['country']] += $row['total_calls'];
                } else {
                  $this->exp_code_data_country['total_calls'][$row['id']][$row['country']] = $row['total_calls'];
                }
                if(isset($this->exp_code_data_country['km_used'][$row['id']][$row['country']])){
                  $this->exp_code_data_country['km_used'][$row['id']][$row['country']] += $row['km_used_count'];
                } else {
                  $this->exp_code_data_country['km_used'][$row['id']][$row['country']] = $row['km_used_count'];
                }
                if(isset($this->exp_code_data_country['al_used'][$row['id']][$row['country']])){
                  $this->exp_code_data_country['al_used'][$row['id']][$row['country']] += $row['al_used_count'];
                } else {
                  $this->exp_code_data_country['al_used'][$row['id']][$row['country']] = $row['al_used_count'];
                }
                if(isset($this->exp_code_data_country['proa'][$row['id']][$row['country']])){
                  $this->exp_code_data_country['proa'][$row['id']][$row['country']] += $row['proa_total'];
                } else {
                  $this->exp_code_data_country['proa'][$row['id']][$row['country']] = $row['proa_total'];
                }
                if(isset($this->exp_code_data_country['proa_pf'][$row['id']][$row['country']])){
                  $this->exp_code_data_country['proa_pf'][$row['id']][$row['country']] += $row['proa_count'];
                } else {
                  $this->exp_code_data_country['proa_pf'][$row['id']][$row['country']] = $row['proa_count'];
                }
              }
            }
  
         } else { // If not Exp Code
  
           $this->total = array();
           $this->total['phone_fix'] = 0;
           $this->total['site_visit'] = 0;
           $this->total['total_calls'] = 0;
           $this->total['km_used'] = 0;
           $this->total['al_used'] = 0;
           $this->total['proa'] = 0;
           $this->total['proa_pf'] = 0;
           $this->total['pf_labor_hours'] = 0;
           $this->total_pl = array();
           $this->total_pl['phone_fix'] = array();
           $this->total_pl['site_visit'] = array();
           $this->total_pl['total_calls'] = array();
           $this->total_pl['km_used'] = array();
           $this->total_pl['al_used'] = array();
           $this->total_pl['proa'] = array();
           $this->total_pl['proa_pf'] = array();
           $this->total_pl['pf_labor_hours'] = array();
           $this->total_period = array();
           $this->total_period['phone_fix'] = array();
           $this->total_period['site_visit'] = array();
           $this->total_period['total_calls'] = array();
           $this->total_period['km_used'] = array();
           $this->total_period['al_used'] = array();
           $this->total_period['proa'] = array();
           $this->total_period['proa_pf'] = array();
           $this->total_period['pf_labor_hours'] = array();
           $this->total_pl_period = array();
           $this->total_pl_period['phone_fix'] = array();
           $this->total_pl_period['site_visit'] = array();
           $this->total_pl_period['total_calls'] = array();
           $this->total_pl_period['km_used'] = array();
           $this->total_pl_period['al_used'] = array();
           $this->total_pl_period['proa'] = array();
           $this->total_pl_period['proa_pf'] = array();
           $this->total_pl_period['pf_labor_hours'] = array();
  
           $this->entity = array();
           $this->entity['phone_fix'] = array();
           $this->entity['site_visit'] = array();
           $this->entity['total_calls'] = array();
           $this->entity['km_used'] = array();
           $this->entity['al_used'] = array();
           $this->entity['proa'] = array();
           $this->entity['proa_pf'] = array();
           $this->entity['pf_labor_hours'] = array();
           $this->entity_country = array();
           $this->entity_country['phone_fix'] = array();
           $this->entity_country['site_visit'] = array();
           $this->entity_country['total_calls'] = array();
           $this->entity_country['km_used'] = array();
           $this->entity_country['al_used'] = array();
           $this->entity_country['proa'] = array();
           $this->entity_country['proa_pf'] = array();
           $this->entity_country['pf_labor_hours'] = array();
           $this->entity_pl = array();
           $this->entity_pl['phone_fix'] = array();
           $this->entity_pl['site_visit'] = array();
           $this->entity_pl['total_calls'] = array();
           $this->entity_pl['km_used'] = array();
           $this->entity_pl['al_used'] = array();
           $this->entity_pl['proa'] = array();
           $this->entity_pl['proa_pf'] = array();
           $this->entity_pl['pf_labor_hours'] = array();
           $this->entity_period = array();
           $this->entity_period['phone_fix'] = array();
           $this->entity_period['site_visit'] = array();
           $this->entity_period['total_calls'] = array();
           $this->entity_period['km_used'] = array();
           $this->entity_period['al_used'] = array();
           $this->entity_period['proa'] = array();
           $this->entity_period['proa_pf'] = array();
           $this->entity_period['pf_labor_hours'] = array();
           $this->entity_pl_period = array();
           $this->entity_pl_period['phone_fix'] = array();
           $this->entity_pl_period['site_visit'] = array();
           $this->entity_pl_period['total_calls'] = array();
           $this->entity_pl_period['km_used'] = array();
           $this->entity_pl_period['al_used'] = array();
           $this->entity_pl_period['proa'] = array();
           $this->entity_pl_period['proa_pf'] = array();
           $this->entity_pl_period['pf_labor_hours'] = array();
           foreach($result_array as $row){
             // Total
             $this->total['phone_fix'] += $row['phone_fix_count'];
             $this->total['site_visit'] += $row['site_visit_count'];
             $this->total['total_calls'] += $row['total_calls'];
             $this->total['km_used'] += $row['km_used_count'];
             $this->total['al_used'] += $row['al_used_count'];
             $this->total['proa'] += $row['proa_total'];
             $this->total['proa_pf'] += $row['proa_count'];
             $this->total['proa_pf'] += $row['pf_labor_hours'];
           
             // Total By PL
             if(isset($this->total_pl['phone_fix'][$row['pl']])){
               $this->total_pl['phone_fix'][$row['pl']] += $row['phone_fix_count'];
             } else {
               $this->total_pl['phone_fix'][$row['pl']] = $row['phone_fix_count'];
             }
             if(isset($this->total_pl['site_visit'][$row['pl']])){
               $this->total_pl['site_visit'][$row['pl']] += $row['site_visit_count'];
             } else {
               $this->total_pl['site_visit'][$row['pl']] = $row['site_visit_count'];
             }
             if(isset($this->total_pl['total_calls'][$row['pl']])){
               $this->total_pl['total_calls'][$row['pl']] += $row['total_calls'];
             } else {
               $this->total_pl['total_calls'][$row['pl']] = $row['total_calls'];
             }
             if(isset($this->total_pl['km_used'][$row['pl']])){
               $this->total_pl['km_used'][$row['pl']] += $row['km_used_count'];
             } else {
               $this->total_pl['km_used'][$row['pl']] = $row['km_used_count'];
             }
             if(isset($this->total_pl['al_used'][$row['pl']])){
               $this->total_pl['al_used'][$row['pl']] += $row['al_used_count'];
             } else {
               $this->total_pl['al_used'][$row['pl']] = $row['al_used_count'];
             }
             if(isset($this->total_pl['proa'][$row['pl']])){
               $this->total_pl['proa'][$row['pl']] += $row['proa_total'];
             } else {
               $this->total_pl['proa'][$row['pl']] = $row['proa_total'];
             }
             if(isset($this->total_pl['proa_pf'][$row['pl']])){
               $this->total_pl['proa_pf'][$row['pl']] += $row['proa_count'];
             } else {
               $this->total_pl['proa_pf'][$row['pl']] = $row['proa_count'];
             }
             if(isset($this->total_pl['pf_labor_hours'][$row['pl']])){
               $this->total_pl['pf_labor_hours'][$row['pl']] += $row['pf_labor_hours'];
             } else {
               $this->total_pl['pf_labor_hours'][$row['pl']] = $row['pf_labor_hours'];
             }
           
             // Total By period
             if(isset($this->total_period['phone_fix'][$row['period']])){
               $this->total_period['phone_fix'][$row['period']] += $row['phone_fix_count'];
             } else {
               $this->total_period['phone_fix'][$row['period']] = $row['phone_fix_count'];
             }
             if(isset($this->total_period['site_visit'][$row['period']])){
               $this->total_period['site_visit'][$row['period']] += $row['site_visit_count'];
             } else {
               $this->total_period['site_visit'][$row['period']] = $row['site_visit_count'];
             }
             if(isset($this->total_period['total_calls'][$row['period']])){
               $this->total_period['total_calls'][$row['period']] += $row['total_calls'];
             } else {
               $this->total_period['total_calls'][$row['period']] = $row['total_calls'];
             }
             if(isset($this->total_period['km_used'][$row['period']])){
               $this->total_period['km_used'][$row['period']] += $row['km_used_count'];
             } else {
               $this->total_period['km_used'][$row['period']] = $row['km_used_count'];
             }
             if(isset($this->total_period['al_used'][$row['period']])){
               $this->total_period['al_used'][$row['period']] += $row['al_used_count'];
             } else {
               $this->total_period['al_used'][$row['period']] = $row['al_used_count'];
             }
             if(isset($this->total_period['proa'][$row['period']])){
               $this->total_period['proa'][$row['period']] += $row['proa_total'];
             } else {
               $this->total_period['proa'][$row['period']] = $row['proa_total'];
             }
             if(isset($this->total_period['proa_pf'][$row['period']])){
               $this->total_period['proa_pf'][$row['period']] += $row['proa_count'];
             } else {
               $this->total_period['proa_pf'][$row['period']] = $row['proa_count'];
             }
             if(isset($this->total_period['pf_labor_hours'][$row['period']])){
               $this->total_period['pf_labor_hours'][$row['period']] += $row['pf_labor_hours'];
             } else {
               $this->total_period['pf_labor_hours'][$row['period']] = $row['pf_labor_hours'];
             }
           
             // Total By pl period
             if(isset($this->total_pl_period['phone_fix'][$row['pl']][$row['period']])){
               $this->total_pl_period['phone_fix'][$row['pl']][$row['period']] += $row['phone_fix_count'];
             } else {
               $this->total_pl_period['phone_fix'][$row['pl']][$row['period']] = $row['phone_fix_count'];
             }
             if(isset($this->total_pl_period['site_visit'][$row['pl']][$row['period']])){
               $this->total_pl_period['site_visit'][$row['pl']][$row['period']] += $row['site_visit_count'];
             } else {
               $this->total_pl_period['site_visit'][$row['pl']][$row['period']] = $row['site_visit_count'];
             }
             if(isset($this->total_pl_period['total_calls'][$row['pl']][$row['period']])){
               $this->total_pl_period['total_calls'][$row['pl']][$row['period']] += $row['total_calls'];
             } else {
               $this->total_pl_period['total_calls'][$row['pl']][$row['period']] = $row['total_calls'];
             }
             if(isset($this->total_pl_period['km_used'][$row['pl']][$row['period']])){
               $this->total_pl_period['km_used'][$row['pl']][$row['period']] += $row['km_used_count'];
             } else {
               $this->total_pl_period['km_used'][$row['pl']][$row['period']] = $row['km_used_count'];
             }
             if(isset($this->total_pl_period['al_used'][$row['pl']][$row['period']])){
               $this->total_pl_period['al_used'][$row['pl']][$row['period']] += $row['al_used_count'];
             } else {
               $this->total_pl_period['al_used'][$row['pl']][$row['period']] = $row['al_used_count'];
             }
             if(isset($this->total_pl_period['proa'][$row['pl']][$row['period']])){
               $this->total_pl_period['proa'][$row['pl']][$row['period']] += $row['proa_total'];
             } else {
               $this->total_pl_period['proa'][$row['pl']][$row['period']] = $row['proa_total'];
             }
             if(isset($this->total_pl_period['proa_pf'][$row['pl']][$row['period']])){
               $this->total_pl_period['proa_pf'][$row['pl']][$row['period']] += $row['proa_count'];
             } else {
               $this->total_pl_period['proa_pf'][$row['pl']][$row['period']] = $row['proa_count'];
             }
             if(isset($this->total_pl_period['pf_labor_hours'][$row['pl']][$row['period']])){
               $this->total_pl_period['pf_labor_hours'][$row['pl']][$row['period']] += $row['pf_labor_hours'];
             } else {
               $this->total_pl_period['pf_labor_hours'][$row['pl']][$row['period']] = $row['pf_labor_hours'];
             }
           
             // Total By Person
             if(isset($this->entity['phone_fix'][$row['entity']])){
               $this->entity['phone_fix'][$row['entity']] += $row['phone_fix_count'];
             } else {
               $this->entity['phone_fix'][$row['entity']] = $row['phone_fix_count'];
             }
             if(isset($this->entity['site_visit'][$row['entity']])){
               $this->entity['site_visit'][$row['entity']] += $row['site_visit_count'];
             } else {
               $this->entity['site_visit'][$row['entity']] = $row['site_visit_count'];
             }
             if(isset($this->entity['total_calls'][$row['entity']])){
               $this->entity['total_calls'][$row['entity']] += $row['total_calls'];
             } else {
               $this->entity['total_calls'][$row['entity']] = $row['total_calls'];
             }
             if(isset($this->entity['km_used'][$row['entity']])){
               $this->entity['km_used'][$row['entity']] += $row['km_used_count'];
             } else {
               $this->entity['km_used'][$row['entity']] = $row['km_used_count'];
             }
             if(isset($this->entity['al_used'][$row['entity']])){
               $this->entity['al_used'][$row['entity']] += $row['al_used_count'];
             } else {
               $this->entity['al_used'][$row['entity']] = $row['al_used_count'];
             }
             if(isset($this->entity['proa'][$row['entity']])){
               $this->entity['proa'][$row['entity']] += $row['proa_total'];
             } else {
               $this->entity['proa'][$row['entity']] = $row['proa_total'];
             }
             if(isset($this->entity['proa_pf'][$row['entity']])){
               $this->entity['proa_pf'][$row['entity']] += $row['proa_count'];
             } else {
               $this->entity['proa_pf'][$row['entity']] = $row['proa_count'];
             }
             if(isset($this->entity['pf_labor_hours'][$row['entity']])){
               $this->entity['pf_labor_hours'][$row['entity']] += $row['pf_labor_hours'];
             } else {
               $this->entity['pf_labor_hours'][$row['entity']] = $row['pf_labor_hours'];
             }
             // entity PL
             //
             if(isset($this->entity_pl['phone_fix'][$row['entity']][$row['pl']])){
               $this->entity_pl['phone_fix'][$row['entity']][$row['pl']] += $row['phone_fix_count'];
             } else {
               $this->entity_pl['phone_fix'][$row['entity']][$row['pl']] = $row['phone_fix_count'];
             }
             if(isset($this->entity_pl['site_visit'][$row['entity']][$row['pl']])){
               $this->entity_pl['site_visit'][$row['entity']][$row['pl']] += $row['site_visit_count'];
             } else {
               $this->entity_pl['site_visit'][$row['entity']][$row['pl']] = $row['site_visit_count'];
             }
             if(isset($this->entity_pl['total_calls'][$row['entity']][$row['pl']])){
               $this->entity_pl['total_calls'][$row['entity']][$row['pl']] += $row['total_calls'];
             } else {
               $this->entity_pl['total_calls'][$row['entity']][$row['pl']] = $row['total_calls'];
             }
             if(isset($this->entity_pl['km_used'][$row['entity']][$row['pl']])){
               $this->entity_pl['km_used'][$row['entity']][$row['pl']] += $row['km_used_count'];
             } else {
               $this->entity_pl['km_used'][$row['entity']][$row['pl']] = $row['km_used_count'];
             }
             if(isset($this->entity_pl['al_used'][$row['entity']][$row['pl']])){
               $this->entity_pl['al_used'][$row['entity']][$row['pl']] += $row['al_used_count'];
             } else {
               $this->entity_pl['al_used'][$row['entity']][$row['pl']] = $row['al_used_count'];
             }
             if(isset($this->entity_pl['proa'][$row['entity']][$row['pl']])){
               $this->entity_pl['proa'][$row['entity']][$row['pl']] += $row['proa_total'];
             } else {
               $this->entity_pl['proa'][$row['entity']][$row['pl']] = $row['proa_total'];
             }
             if(isset($this->entity_pl['proa_pf'][$row['entity']][$row['pl']])){
               $this->entity_pl['proa_pf'][$row['entity']][$row['pl']] += $row['proa_count'];
             } else {
               $this->entity_pl['proa_pf'][$row['entity']][$row['pl']] = $row['proa_count'];
             }
             if(isset($this->entity_pl['pf_labor_hours'][$row['entity']][$row['pl']])){
               $this->entity_pl['pf_labor_hours'][$row['entity']][$row['pl']] += $row['pf_labor_hours'];
             } else {
               $this->entity_pl['pf_labor_hours'][$row['entity']][$row['pl']] = $row['pf_labor_hours'];
             }
           
             // entity country
             //
             if(isset($this->entity_country['phone_fix'][$row['entity']][$row['country']])){
               $this->entity_country['phone_fix'][$row['entity']][$row['country']] += $row['phone_fix_count'];
             } else {
               $this->entity_country['phone_fix'][$row['entity']][$row['country']] = $row['phone_fix_count'];
             }
             if(isset($this->entity_country['site_visit'][$row['entity']][$row['country']])){
               $this->entity_country['site_visit'][$row['entity']][$row['country']] += $row['site_visit_count'];
             } else {
               $this->entity_country['site_visit'][$row['entity']][$row['country']] = $row['site_visit_count'];
             }
             if(isset($this->entity_country['total_calls'][$row['entity']][$row['country']])){
               $this->entity_country['total_calls'][$row['entity']][$row['country']] += $row['total_calls'];
             } else {
               $this->entity_country['total_calls'][$row['entity']][$row['country']] = $row['total_calls'];
             }
             if(isset($this->entity_country['km_used'][$row['entity']][$row['country']])){
               $this->entity_country['km_used'][$row['entity']][$row['country']] += $row['km_used_count'];
             } else {
               $this->entity_country['km_used'][$row['entity']][$row['country']] = $row['km_used_count'];
             }
             if(isset($this->entity_country['al_used'][$row['entity']][$row['country']])){
               $this->entity_country['al_used'][$row['entity']][$row['country']] += $row['al_used_count'];
             } else {
               $this->entity_country['al_used'][$row['entity']][$row['country']] = $row['al_used_count'];
             }
             if(isset($this->entity_country['proa'][$row['entity']][$row['country']])){
               $this->entity_country['proa'][$row['entity']][$row['country']] += $row['proa_total'];
             } else {
               $this->entity_country['proa'][$row['entity']][$row['country']] = $row['proa_total'];
             }
             if(isset($this->entity_country['proa_pf'][$row['entity']][$row['country']])){
               $this->entity_country['proa_pf'][$row['entity']][$row['country']] += $row['proa_count'];
             } else {
               $this->entity_country['proa_pf'][$row['entity']][$row['country']] = $row['proa_count'];
             }
             if(isset($this->entity_country['pf_labor_hours'][$row['entity']][$row['country']])){
               $this->entity_country['pf_labor_hours'][$row['entity']][$row['country']] += $row['pf_labor_hours'];
             } else {
               $this->entity_country['pf_labor_hours'][$row['entity']][$row['country']] = $row['pf_labor_hours'];
             }
           
             // entity periods
             //
             if(isset($this->entity_period['phone_fix'][$row['entity']][$row['period']])){
               $this->entity_period['phone_fix'][$row['entity']][$row['period']] += $row['phone_fix_count'];
             } else {
               $this->entity_period['phone_fix'][$row['entity']][$row['period']] = $row['phone_fix_count'];
             }
             if(isset($this->entity_period['site_visit'][$row['entity']][$row['period']])){
               $this->entity_period['site_visit'][$row['entity']][$row['period']] += $row['site_visit_count'];
             } else {
               $this->entity_period['site_visit'][$row['entity']][$row['period']] = $row['site_visit_count'];
             }
             if(isset($this->entity_period['total_calls'][$row['entity']][$row['period']])){
               $this->entity_period['total_calls'][$row['entity']][$row['period']] += $row['total_calls'];
             } else {
               $this->entity_period['total_calls'][$row['entity']][$row['period']] = $row['total_calls'];
             }
             if(isset($this->entity_period['km_used'][$row['entity']][$row['period']])){
               $this->entity_period['km_used'][$row['entity']][$row['period']] += $row['km_used_count'];
             } else {
               $this->entity_period['km_used'][$row['entity']][$row['period']] = $row['km_used_count'];
             }
             if(isset($this->entity_period['al_used'][$row['entity']][$row['period']])){
               $this->entity_period['al_used'][$row['entity']][$row['period']] += $row['al_used_count'];
             } else {
               $this->entity_period['al_used'][$row['entity']][$row['period']] = $row['al_used_count'];
             }
             if(isset($this->entity_period['proa'][$row['entity']][$row['period']])){
               $this->entity_period['proa'][$row['entity']][$row['period']] += $row['proa_total'];
             } else {
               $this->entity_period['proa'][$row['entity']][$row['period']] = $row['proa_total'];
             }
             if(isset($this->entity_period['proa_pf'][$row['entity']][$row['period']])){
               $this->entity_period['proa_pf'][$row['entity']][$row['period']] += $row['proa_count'];
             } else {
               $this->entity_period['proa_pf'][$row['entity']][$row['period']] = $row['proa_count'];
             }
             if(isset($this->entity_period['pf_labor_hours'][$row['entity']][$row['period']])){
               $this->entity_period['pf_labor_hours'][$row['entity']][$row['period']] += $row['pf_labor_hours'];
             } else {
               $this->entity_period['pf_labor_hours'][$row['entity']][$row['period']] = $row['pf_labor_hours'];
             }
           
             // entity pl periods
             //
             if(isset($this->entity_pl_period['phone_fix'][$row['entity']][$row['pl']][$row['period']])){
               $this->entity_pl_period['phone_fix'][$row['entity']][$row['pl']][$row['period']] += $row['phone_fix_count'];
             } else {
               $this->entity_pl_period['phone_fix'][$row['entity']][$row['pl']][$row['period']] = $row['phone_fix_count'];
             }
             if(isset($this->entity_pl_period['site_visit'][$row['entity']][$row['pl']][$row['period']])){
               $this->entity_pl_period['site_visit'][$row['entity']][$row['pl']][$row['period']] += $row['site_visit_count'];
             } else {
               $this->entity_pl_period['site_visit'][$row['entity']][$row['pl']][$row['period']] = $row['site_visit_count'];
             }
             if(isset($this->entity_pl_period['total_calls'][$row['entity']][$row['pl']][$row['period']])){
               $this->entity_pl_period['total_calls'][$row['entity']][$row['pl']][$row['period']] += $row['total_calls'];
             } else {
               $this->entity_pl_period['total_calls'][$row['entity']][$row['pl']][$row['period']] = $row['total_calls'];
             }
             if(isset($this->entity_pl_period['km_used'][$row['entity']][$row['pl']][$row['period']])){
               $this->entity_pl_period['km_used'][$row['entity']][$row['pl']][$row['period']] += $row['km_used_count'];
             } else {
               $this->entity_pl_period['km_used'][$row['entity']][$row['pl']][$row['period']] = $row['km_used_count'];
             }
             if(isset($this->entity_pl_period['al_used'][$row['entity']][$row['pl']][$row['period']])){
               $this->entity_pl_period['al_used'][$row['entity']][$row['pl']][$row['period']] += $row['al_used_count'];
             } else {
               $this->entity_pl_period['al_used'][$row['entity']][$row['pl']][$row['period']] = $row['al_used_count'];
             }
             if(isset($this->entity_pl_period['proa'][$row['entity']][$row['pl']][$row['period']])){
               $this->entity_pl_period['proa'][$row['entity']][$row['pl']][$row['period']] += $row['proa_total'];
             } else {
               $this->entity_pl_period['proa'][$row['entity']][$row['pl']][$row['period']] = $row['proa_total'];
             }
             if(isset($this->entity_pl_period['proa_pf'][$row['entity']][$row['pl']][$row['period']])){
               $this->entity_pl_period['proa_pf'][$row['entity']][$row['pl']][$row['period']] += $row['proa_count'];
             } else {
               $this->entity_pl_period['proa_pf'][$row['entity']][$row['pl']][$row['period']] = $row['proa_count'];
             }
             if(isset($this->entity_pl_period['pf_labor_hours'][$row['entity']][$row['pl']][$row['period']])){
               $this->entity_pl_period['pf_labor_hours'][$row['entity']][$row['pl']][$row['period']] += $row['pf_labor_hours'];
             } else {
               $this->entity_pl_period['pf_labor_hours'][$row['entity']][$row['pl']][$row['period']] = $row['pf_labor_hours'];
             }
           }
  
         }
  
      }


      /*     // Add total closed calls reactive to bucket array.
        $this->prr_bucket_category_array[] = 'Total Closed Calls - Reactive';
        // Add total closed calls to bucket array.
        $this->prr_bucket_category_array[] = 'Total Closed Calls';
  
        foreach($this->prr_bucket_category_array as $prr_bucket){
            if(isset($this->data[$prr_bucket])){
                foreach($this->period_array as $mo){
                  if(array_key_exists($mo,$this->data[$prr_bucket])){
                      $this->call_data[$prr_bucket][] = $this->data[$prr_bucket][$mo];
                  } else {
                      $this->call_data[$prr_bucket][] = 0;
                  }
                }
            } else {
                foreach($this->period_array as $mo){
                  $this->call_data[$prr_bucket][] = 0;
                }
            }
        }
  
         //pr($this->call_data);
        // Fill out Census Array
        foreach($this->period_array as $mo){
          // Census
          if(array_key_exists($mo,$this->census)){
              $this->census_data[] = $this->census[$mo];
          } else {
              $this->census_data[] = 0;
          }
        }
        */
    return true;
  }
  
  
  
  protected function _build_sql(){
    $this->chart_elements_desc = "";
 
    //echo "Final Where: ". $this->final_where  ."<br>";   $this->db_obj->run_sql_with_exit_on_error($this->SQL);
    /* Commenting this out as we are using a built in flag in the main table.
    $this->SQL ="
        select distinct login
         into #open_by_logins
        from GSR_ADMIN.dbo.GSR_ADMIN_REF_LOGIN_CENTER
        UNION
        select GSR_OPTION_VALUE
        from GSR_ADMIN.dbo.GSR_Option_Config
        where GSR_OPTION_GROUP = 'CALL_CENTER_REPORT'
          and GSR_OPTION_NAME = 'SYSTEMS_LOGIN'
          and COMMENT = '1';
      ";
     $rs = $this->db_obj->run_sql_with_exit_on_error($this->SQL);
     $rs= null;
     */
     $this->SQL = $this->select . $this->select_date_interval . "
       sum(S.phone_fix) as phone_fix_count,
       sum(S.site_visit) as site_visit_count,
       sum(S.phone_fix) + sum(site_visit) as total_calls,
       sum(S.km_used) as km_used_count,
       sum(case when S.phone_fix > 0 then S.is_Proactive else 0 end) as proa_count,
       sum(S.is_Proactive) as proa_total,
       sum(S.abbottlink_used_flag) as al_used_count
       ,sum(s.pf_labor_hours) as pf_labor_hours
 
    FROM GSR.dbo.GSR_METRIC_SUMMARY_CC_SERV2 S
      inner join reliability.dbo.productline pl on
        S.pl = pl.pl
      inner join GSR_ADMIN.dbo.GSR_ADMIN_REF_LOGIN_CENTER l on
        S.csc_handledby = l.login
        $this->extra_join
      -- This is commented out and using the ticket_openedby_tso field to do
      -- the same thing int he where clause below.
      --inner join #open_by_logins l2 on
      --    S.ticket_openedby_login = l2.login
 
    ".
    $this->final_where." " .
    " and S.receipt_date between l.Start_date and coalesce(l.End_date, format(getdate(), 'yyyy-MM-dd')) and s.ticket_openedby_tso = 1 " .
    $this->groupby ." ,". $this->groupby_date_interval ." ".
 
    $this->orderby . " ";
 
    // If we are doinf the experience code thing, lets build an UP/DOWN sql bit.
    if($this->REQUEST['report_type'] == 'prr_ex') {
      $this->SQL2 = $this->select2 . "
           sum(S.phone_fix) as phone_fix_count,
           sum(S.site_visit) as site_visit_count,
           sum(S.phone_fix) + sum(site_visit) as total_calls
          FROM GSR.dbo.GSR_METRIC_SUMMARY_CC_SERV2 S
          inner join reliability.dbo.productline pl on
            S.pl = pl.pl
          inner join GSR_ADMIN.dbo.GSR_ADMIN_REF_LOGIN_CENTER l on
            S.csc_handledby = l.login
 
      ".
          $this->final_where." " .
          " and S.receipt_date between l.Start_date and coalesce(l.End_date, format(getdate(), 'yyyy-MM-dd'))  and s.ticket_openedby_tso = 1 " .
          $this->groupby2 ." ";
          //pr($this->SQL2);
    }
 
    //exit;
  }   // end of _build_sql
  
  public function get_sql(){
    return $this->SQL;
  }
  
  public function get_ticket_list_sql(){
    return $this->sql_ticket_list;
  }
  
  public function get_chart_data(){
    return $this->chart_data;
  }
  
  public function get_call_data(){
    return $this->call_data;
  }
  
  public function get_chart_periods(){
    return $this->chart_period_array;
  }
  
  public function get_chart_title_elements(){
      // Lets build the title description for items in the chart.
    return $this->chart_elements_desc;
  }
  
  
  public function start_chart($period, $chart_mapping=true, $title){
  
    // Let see if we will be doing a chart map.
    $this->ChartMapping = $chart_mapping;
 
    // Set chart size
    $width = 720;
    $height = 400;
    //Create a XYChart of size 420 pixels x 240 pixels
    $this->chart = new XYChart($width, $height);
 
    //Set the plotarea at (70, 50) and of size 320 x 150 pixels. Set background
    $this->chart->setPlotArea(40, 55, $width - 80, $height - 120, 0xFFFFFF, 0xEFEFEF, 0xc0c0c0, 0xc0c0c0);
 
    if(isset($title)){
      $titleBox = $this->chart->addTitle("$title", GSR_CHART_TITLE_FONT, GSR_CHART_TITLE_FONT_SIZE-4, GSR_CHART_COLOR_TITLE);
      $titleBox->setMaxWidth(($width-20));
      $this->titleH = $titleBox->getHeight();
 
      if($this->titleH >= $height){
          $titleBox->setFontSize(GSR_CHART_TITLE_FONT_SIZE-8);
          $this->titleH = $titleBox->getHeight();
      }
    }
  
    $labelsObj = $this->chart->xAxis->setLabels($period);
    $labelsObj->setFontAngle(90);
  
    //Set the x-axis width to 2 pixels
    $this->chart->xAxis->setWidth(2);
  
    //Set the y axis label format to US$nnnn
    $this->chart->yAxis->setLabelFormat("{value}%");
    $this->chart->yAxis->setLinearScale(0, 100, 10);
  
    //Set the y axis title
    $this->chart->yAxis->setTitle("% of Resolution");
  
    //Set the y-axis width to 2 pixels
    $this->chart->yAxis->setWidth(2);
  
    //GSR_DATE_FORMAT_LABEL
  }
  
  public function add_legend_to_chart(){
    //Add a legend box at the top of the plotarea
    $this->legend = $this->chart->addLegend2(50, 20, -2, "", 8);
    $this->legend->setBackground(Transparent, Transparent);
  
    $legendY     = $this->titleH-8;
    //$legendX    = ($yAxisRL+5);
    $this->legend->setPos(50, $legendY);
  
    //Add a line chart layer using the supplied data
    $this->layer = $this->chart->addLineLayer2();
    $this->layer->setLineWidth(2);
  }
  
  public function add_line_to_chart($data, $color, $name, $shape, $size, $line_style = "solid", $goal = false){
  
    if($line_style == "solid"){
      $dataSetObj = $this->layer->addDataSet($data, "$color", $name);
      //if(!$goal){
      //  $dataSetObj->setDataSymbol($shape, $size);
      //}
    } else {
      $dataSetObj = $this->layer->addDataSet($data, $this->chart->dashLineColor("$color", DashLine), $name);
      //$dataSetObj->setDataSymbol($shape, $size);
    }
  
  }
  
  public function gen_chart($pdf = false){
  
    if($pdf){
        $chart['graph'] = $this->chart->makeChart2(2);
    } else {
      // layout the legend so we can get the height of the legend box
      $this->chart->layoutLegend();
      // Adjust the plot area size, such that the bounding box (inclusive of axes) is 15
      // pixels from the left edge, just under the legend box, 16 pixels from the right
      // edge, and 25 pixels from the bottom edge.
      $plotY = ($this->titleH + ($this->legend->getHeight()));
      $this->chart->packPlotArea(15, $plotY, $this->chart->getWidth() - 16, $this->chart->getHeight() - 25);
      $chart['graph'] = $this->chart->makeSession("chart1");
      //Create an image map for the chart
      //if($this->ChartMapping ){
      //  $chart['map'] = $this->chart->getHTMLImageMap("javascript:CountCheck('{dataSetName}','{xLabel}');", " ",
       //                                               "title='{dataSetName}\n{value} Calls/Year\n{xLabel}'");
      //}
  
    }
    return $chart;
  }
} // END class country_reports
?>