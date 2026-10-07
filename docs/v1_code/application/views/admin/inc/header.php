<?php
$company = $this->db->query('select * from tbl_company')->row();
$permissions = json_decode($this->session->userdata('permissions'));
$type = $this->session->userdata('type');
$access = isset($permissions) ? $permissions : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta charset="utf-8" />
    <title><?php echo $company->name; ?> - <?php echo $title; ?></title>

    <meta name="description" content="overview &amp; stats" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />

    <!-- bootstrap & fontawesome -->
    <link rel="stylesheet" href="<?php echo base_url().'assets/css/bootstrap.min.css'?>" />
    <link rel="stylesheet" href="<?php echo base_url().'assets/font-awesome/4.5.0/css/font-awesome.min.css' ?>" />

    <!-- page specific plugin styles -->

    <!-- text fonts -->
    <link rel="stylesheet" href="<?php echo base_url().'assets/css/fonts.googleapis.com.css' ?> " />
    <link rel="stylesheet" href="<?php echo base_url('assets/fancyBox/css/jquery.fancybox.css?v=2.1.5') ?> "
        media="screen" />
    <!-- ace styles -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/ace.min.css') ?> " class="ace-main-stylesheet"
        id="main-ace-style" />

    <!--[if lte IE 9]>
      <link rel="stylesheet" href="assets/css/ace-part2.min.css" class="ace-main-stylesheet" />
    <![endif]-->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/ace-skins.min.css') ?> " />
    <link rel="stylesheet" href="<?php echo base_url('assets/css/ace-rtl.min.css') ?> " />
    <!--hi-->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css') ?> " />

    <link rel="stylesheet" href="<?php echo base_url('assets/css/chosen.min.css') ?> ">

    <link rel="stylesheet" href="<?php echo base_url('assets/docsupport/prism.css') ?> ">
    <link rel="stylesheet" href="<?php echo base_url('assets/docsupport/chosen.css') ?> ">

    
    <!-- ace settings handler -->
    <script src="<?php echo base_url('assets/js/ace-extra.min.js') ?> "></script>
    <script src="<?php echo base_url(); ?>assets/js/jquery-2.1.4.min.js"></script>

    <style type="text/css">
    .header {
        /*border: 1px solid;*/
        width: 100%;
        height: 150px;
        margin-top: 0px;
    }

    .img {
        height: 120px;
        width: 220px;
        margin-top: 0px;
        /*border: 1px solid;*/
    }

    .section3 {
        /*border: 1px solid;*/
        /* height: 150px; */
    }

    .section12 {
        height: 100px;
        width: 100%;
        margin-top: 5px;
        border-radius: 5px;
        background-color: #A7ECFB;
        border: 1px solid #0A829B;
    }

    .logo {
        /*border: 1px solid;*/
        height: 75px;
        width: 100%;
        font-size: 40px;
        text-align: center;
        margin-top: 5px;
    }

    .textModule {
        height: auto;
        width: 100%;
        margin-top: -12px;
        font-weight: bold;
        font-size: 13px;
        color: #000;
        text-align: center;
    }
    </style>

    <style type="text/css">
    .header {
        /*border: 1px solid;*/
        width: 100%;
        height: 150px;
    }

    .img {
        height: 120px;
        width: 220px;
        margin-top: 0px;
        /*border: 1px solid;*/
    }

    .txtBody {
        height: auto;
        width: 100%;
        margin-top: 5px;
        font-weight: bold;
        font-size: 70px;
        color: #1A7EB0;
        text-align: center;
    }

    a {
        color: #333;
    }
    </style>
</head>

<body class="skin-2" style="overflow-x: hidden">

    <div id="navbar" class="navbar navbar-default ace-save-state navbar-fixed-top"
        style="background:#438EB9 !important;">
        <div class="navbar-container ace-save-state" id="navbar-container">
            <button type="button" class="navbar-toggle menu-toggler pull-left" id="menu-toggler" data-target="#sidebar">
                <span class="sr-only">Toggle sidebar</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>

            <div class="navbar-header pull-left">
                <a href="" class="navbar-brand">
                    <small>
                        <i class="fa fa-leaf"></i>
                        <?php echo $company->name; ?>
                    </small>
                </a>
            </div>

            <div class="navbar-buttons navbar-header pull-right" role="navigation">
                <ul class="nav ace-nav">
                    <li>
                        <a class="clock" style="background:#438EB9 !important;">
                            <span style="font-size:20px;"><i class="ace-icon fa fa-clock-o"></i></span> <span
                                style="font-size:15px;">&nbsp;<span id="timer" style="font-size:15px;"></span></span>
                        </a>
                    </li>

                    <li class="light-blue dropdown-modal">
                        <a data-toggle="dropdown" href="#" class="dropdown-toggle">
                            <img class="nav-user-photo" src="assets/user.jpg" alt="Photo" />
                            <span class="user-info">
                                <small>Welcome <br> <?php echo $this->session->userdata('name'); ?></small>
                            </span>

                            <i class="ace-icon fa fa-caret-down"></i>
                        </a>

                        <ul
                            class="user-menu dropdown-menu-right dropdown-menu dropdown-yellow dropdown-caret dropdown-close">
                            <!-- <li>
                  <a href="profile">
                    <i class="ace-icon fa fa-user"></i>
                    Profile
                  </a>
                </li>-->
                            <li class="divider"></li>

                            <li>
                                <a href="<?php echo base_url('logout') ?>">
                                    <i class="ace-icon fa fa-power-off"></i>
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </li>

                </ul>
            </div>
        </div><!-- /.navbar-container -->
    </div>

    <div class="main-container ace-save-state" id="main-container">
        <script type="text/javascript">
        try {
            ace.settings.loadState('main-container')
        } catch (e) {}
        </script>

        <div id="sidebar" class="sidebar responsive ace-save-state sidebar-fixed sidebar-scroll">
            <script type="text/javascript">
            try {
                ace.settings.loadState('sidebar')
            } catch (e) {}
            </script>

            <div class="sidebar-shortcuts" id="sidebar-shortcuts">
                <div class="sidebar-shortcuts-large" id="sidebar-shortcuts-large">
                    <button class="btn btn-success"><i class="ace-icon fa fa-signal"></i></button>
                    <button class="btn btn-info"><i class="ace-icon fa fa-pencil"></i></button>
                    <button class="btn btn-warning"><i class="ace-icon fa fa-users"></i></button>
                    <button class="btn btn-danger"><i class="ace-icon fa fa-cogs"></i></button>
                </div>

                <div class="sidebar-shortcuts-mini" id="sidebar-shortcuts-mini">
                    <span class="btn btn-success"></span>
                    <span class="btn btn-info"></span>
                    <span class="btn btn-warning"></span>
                    <span class="btn btn-danger"></span>
                </div>
            </div><!-- /.sidebar-shortcuts -->
            <?php $type = $this->session->userdata('type'); ?>
            <ul class="nav nav-list">
                <?php if (array_search('dashboard', $access) > -1 || $type == 'a') { ?>
                <li class="active"><a href="<?php echo base_url() ?>"><i class="menu-icon fa fa-tachometer"></i><span
                            class="menu-text"> Dashboard </span></a> </li>
                <?php }?>

                <li class="">
                    <a href="#" class="dropdown-toggle"><i class="menu-icon fa fa-list"></i><span class="menu-text">
                            Client Management </span><b class="arrow fa fa-angle-down"></b></a>
                    <ul class="submenu">
                        <?php if (array_search('prospect-entry', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('prospect-entry') ?>">Prospect Entry </a></li>
                        <?php }?>
                        <?php if (array_search('get-team-client', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('get-team-client') ?>">Prospect List </a></li>
                        <?php }?>
                        <?php if (array_search('sold', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('sold') ?>">Client List </a></li>
                        <?php }?>
                        <?php if (array_search('team-list', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('team-list') ?>"> Team Member </a></li>
                        <?php }?>
                        <?php if (array_search('report', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('report') ?>">Report </a></li>
                        <?php }?>
                    </ul>
                </li>

                <li class="">
                    <a href="#" class="dropdown-toggle"><i class="menu-icon fa fa-list"></i><span class="menu-text">
                            Task Management </span><b class="arrow fa fa-angle-down"></b></a>
                    <ul class="submenu">
                        <?php if (array_search('task', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('task') ?>">Task Entry </a></li>
                        <?php }?>
                        <?php if (array_search('task-record', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('task-record') ?>">Task Record </a></li>
                        <?php }?>
                        <?php if (array_search('my-task', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('my-task') ?>">My Task </a></li>
                        <?php }?>
                        <?php if (array_search('complete-task', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('complete-task') ?>">Completed Task </a></li>
                        <?php }?>
                        <?php if (array_search('archive-task', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('archive-task') ?>">Archived Task </a></li>
                        <?php }?>
                    </ul>
                </li>

                <li class="">
                    <a href="#" class="dropdown-toggle"><i class="menu-icon fa fa-list"></i><span class="menu-text">
                            Acc. Management </span><b class="arrow fa fa-angle-down"></b></a>
                    <ul class="submenu">
                        <?php if (array_search('cashTransaction', $access) > -1 || $type == 'a') { ?>
                        <li class="">
                            <a href="<?php echo base_url(); ?>cashTransaction">
                                <i class="menu-icon fa fa-medkit"></i>
                                <span class="menu-text"> Cash Transaction </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>
                        <?php if (array_search('bank_transactions', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>bank_transactions">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Bank Transactions </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('project_transactions', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>project_transactions">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Client Transactions </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>


                        <?php if (array_search('project_expense', $access) > -1 || $type == 'a') { ?>
                        <li class="">
                            <a href="<?php echo base_url(); ?>project_expense">
                                <i class="menu-icon fa fa-money"></i>
                                <span class="menu-text"> Project Expense </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('vendor_expense', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>vendor_expense">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Vendor Expense </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        
                        <?php if (array_search('vendor_bill_entry', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>vendor_bill_entry">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Vendor Bill Entry </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('vendor_bill_record', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>vendor_bill_record">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Vendor Bill Record </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('project_bill_entry', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>project_bill_entry">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Project Bill Entry </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('project_bill_record', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>project_bill_record">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Project Bill Record </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>



                        <?php if (array_search('account', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>account">
                                <i class="menu-icon fa fa-caret-right"></i>
                                Create Account Holder
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>


                        <?php if (array_search('bank_accounts', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>bank_accounts">
                                <i class="menu-icon fa fa-caret-right"></i>
                                Bank Accounts
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                    </ul>
                </li>
				<li class="">
                    <a href="#" class="dropdown-toggle"><i class="menu-icon fa fa-list"></i><span class="menu-text">
                            Account Ledger </span><b class="arrow fa fa-angle-down"></b></a>
                    <ul class="submenu">
                        <?php if (array_search('TransactionReport', $access) > -1 || $type == 'a') { ?>
                        <li class="">
                            <a href="<?php echo base_url(); ?>TransactionReport">
                                <i class="menu-icon fa fa-medkit"></i>
                                <span class="menu-text"> Cash Transaction Report</span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>
                        <?php if (array_search('bank_transaction_report', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>bank_transaction_report">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Bank Transactions Report </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('project_transactions_report', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>project_transactions_report">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Client Trans Ledger </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('vendor_expense_report', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>vendor_expense_report">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Vendor Expense Report </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        <?php if (array_search('vendor_ledger', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>vendor_ledger">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Vendor Ledger </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        <?php if (array_search('project_ledger', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>project_ledger">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> project Ledger </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        <?php if (array_search('monthly_revenue_report', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>monthly_revenue_report">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Monthly Revenue Report </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>
                        
                        <?php if (array_search('client_ledger', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>client_ledger">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Client Ledger </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>
                        

                        <?php if (array_search('project_expense_report', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>project_expense_report">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Project Expense Report </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('cash_ledger', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>cash_ledger">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Cash Ledger </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('cash_view', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>cash_view">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text"> Cash View </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                        <?php if (array_search('monthly_report', $access) > -1 || $type == 'a') { ?>
                        <li>
                            <a href="<?php echo base_url(); ?>monthly_report">
                                <i class="menu-icon fa fa-dollar"></i>
                                <span class="menu-text">Monthly Report </span>
                            </a>
                            <b class="arrow"></b>
                        </li>
                        <?php }?>

                    </ul>
                </li>

                <li class="">
                    <a href="#" class="dropdown-toggle"><i class="menu-icon fa fa-list"></i><span class="menu-text">
                            Project Operation </span><b class="arrow fa fa-angle-down"></b></a>
                    <ul class="submenu">
                       
                       <?php if (array_search('project_visit', $access) > -1 || $type == 'a') { ?>
                            <li class="">
                                <a href="<?php echo base_url(); ?>project_visit">
                                    <i class="menu-icon fa fa-medkit"></i>
                                    <span class="menu-text"> Project Visit </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        <?php if (array_search('project_visit_record', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>project_visit_record">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Project Visit Record </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        <?php if (array_search('project_matarial_estimate', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>project_matarial_estimate">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Project Material Estimate  </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        
                        <?php if (array_search('project_matarial_estimate_record', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>project_matarial_estimate_record">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Project Material Estimate Record  </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        <?php if (array_search('work_estimate_sheet_entry', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>work_estimate_sheet_entry">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Work Estimate Sheet Entry </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                        <?php if (array_search('work_estimate_sheet_record', $access) > -1 || $type == 'a') { ?>
                            <li>
                                <a href="<?php echo base_url(); ?>work_estimate_sheet_record">
                                    <i class="menu-icon fa fa-dollar"></i>
                                    <span class="menu-text"> Work Estimate Sheet Record </span>
                                </a>
                                <b class="arrow"></b>
                            </li>
                        <?php }?>

                    </ul>
                </li>

              

                <li class="">
                    <a href="#" class="dropdown-toggle"><i class="menu-icon fa fa-cogs"></i><span class="menu-text">
                            Settings </span><b class="arrow fa fa-angle-down"></b></a>
                    <ul class="submenu">
                        <?php if (array_search('user', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('user') ?>"><span class="menu-text"> Create User
                                </span></a></li>
                        <?php }?>
                        <?php if (array_search('area', $access) > -1 || $type == 'a') { ?>
                            <li class=""><a href="<?php echo base_url('area') ?>">Area Entry</a></li>
                        <?php }?>
                        <?php if (array_search('vendor', $access) > -1 || $type == 'a') { ?>
                            <li class=""><a href="<?php echo base_url('vendor') ?>">Vendor Entry</a></li>
                        <?php }?>
                        <?php if (array_search('team', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('team') ?>">Team Entry</a></li>

                        <?php }?>
                        <?php if (array_search('material', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('material') ?>">Material Entry</a></li>
                        <?php }?>
                        
                        <?php if (array_search('requerment', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('requerment') ?>">Service Entry</a></li>
                        <?php }?>
                        <?php if (array_search('project_entry', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('project_entry') ?>">Project Entry</a></li>
                        <?php }?>
                        <?php if (array_search('post', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('post') ?>">Post Entry</a></li>
                        <?php }?>
                        <?php if (array_search('department', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('department') ?>">Department Entry</a></li>
                        <?php }?>
                        <?php if (array_search('employee', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('employee') ?>">Employee Entry</a></li>
                        <?php }?>
                        <?php if (array_search('type', $access) > -1 || $type == 'a') { ?>
                        <li class=""><a href="<?php echo base_url('type') ?>">Type Entry</a></li>
                        <?php }?>
                    </ul>
                </li>
                <?php if (array_search('archive-task', $access) > -1 || $type == 'a') { ?>
                <li class=""><a href="<?php echo base_url('company-profile') ?>"><i
                            class="menu-icon fa fa-bank"></i><span class="menu-text"> Company Profile </span></a></li>
                <?php }?>
                <li class=""><a href="<?php echo base_url('logout') ?>"><i class="menu-icon fa fa-sign-out"></i><span
                            class="menu-text"> Logout </span></a></li>
            </ul>

            <div class="sidebar-toggle sidebar-collapse" id="sidebar-collapse">
                <i id="sidebar-toggle-icon" class="ace-icon fa fa-angle-double-left ace-save-state"
                    data-icon1="ace-icon fa fa-angle-double-left" data-icon2="ace-icon fa fa-angle-double-right"></i>
            </div>
        </div>
        <div class="main-content">
            <div class="container main-content-inner">
                <div class="breadcrumbs ace-save-state" id="breadcrumbs">
                    <ul class="breadcrumb">
                        <li>
                            <i class="ace-icon fa fa-home home-icon"></i>
                            <a href="/dashboard">Home</a>
                        </li>
                        <li class="active"><?php echo $title; ?></li>
                    </ul>
                </div>
