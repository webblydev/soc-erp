<?php

defined('BASEPATH') or exit('No direct script access allowed');
$route['default_controller'] = 'Admin/index';
$route['dashboard'] = 'Dashboard/dashborad';
$route['company-profile'] = 'Dashboard/companyProfile';
$route['get-companyProfile'] = 'Dashboard/getCompanyProfile';
$route['update-profile'] = 'Dashboard/updateCompanyProfile';
$route['login-form'] = 'Admin/login';
$route['logout'] = 'Admin/logout';

// app client entry
$route['app-get-client'] = 'Admin/getAppClients';
$route['app-client-entry'] = 'Admin/appClientEntry';
$route['app-client-update'] = 'Admin/appClientUpdate';

// clients add
$route['prospect-entry'] = 'Client/index';
$route['get-client'] = 'Client/getClient';
$route['get-client-detail'] = 'Client/getClientDetail';
$route['add-client'] = 'Client/addClient';
$route['update-client'] = 'Client/updateClient';
$route['delete-client'] = 'Client/deleteClient';
$route['get-team-client'] = 'Client/getTeamClient';
$route['get_clientdetails'] = 'Client/getClientDetails';

$route['get-reminder'] = 'Client/getReminderClient';
$route['report'] = 'Client/clientReport';

// sold client

$route['sold-client'] = 'Client/clientSold';
$route['sold'] = 'Client/soldClient';
$route['change-status'] = 'Client/changeSoldClient';
$route['client-entry/(:any)'] = 'Client/editSoldStatus/$1';

// area
$route['area'] = 'Area/index';
$route['get-areas'] = 'Area/getAreas';
$route['add-area'] = 'Area/addArea';
$route['update-area'] = 'Area/updateArea';
$route['delete-area'] = 'Area/deletedArea';

// work-Item
$route['workItem'] = 'Work/index';
$route['get-workItems'] = 'Work/getWorkItem';
$route['add-workItem'] = 'Work/addWorkItem';
$route['update-workItem'] = 'Work/updateWorkItem';
$route['delete-workItem'] = 'Work/deletedWorkItem';

// vendor
$route['vendor'] = 'Vendor/index';
$route['get-vendors'] = 'Vendor/getVendors';
$route['add-vendor'] = 'Vendor/addVendor';
$route['update-vendor'] = 'Vendor/updateVendor';
$route['delete-vendor'] = 'Vendor/deletedVendor';

// client type
$route['client_type'] = 'Client/ClientIndex';
$route['get-clientType'] = 'Client/getClientType';
$route['add-clientType'] = 'Client/addClientType';
$route['update-clientType'] = 'Client/updateClientType';
$route['delete-clientType'] = 'Client/deletedClientType';

// create user
$route['user'] = 'Admin/user';
$route['get-users'] = 'Admin/getUsers';
$route['add-user'] = 'Admin/addUser';
$route['update-user'] = 'Admin/updateUser';
$route['delete-user'] = 'Admin/deleteUser';
$route['user-access'] = 'Admin/updateUserPermission';

// team manager
$route['team'] = 'Admin/team';
$route['get-teams'] = 'Admin/getTeams';
$route['add-team'] = 'Admin/addTeam';
$route['update-team'] = 'Admin/updateTeam';
$route['delete-team'] = 'Admin/deleteTeam';
$route['team-list'] = 'Admin/teamList';
$route['get-team-member'] = 'Admin/getTeamMember';

// Material Entry
$route['material'] = 'Admin/material';
$route['get-materials'] = 'Admin/getMaterials';
$route['add-material'] = 'Admin/addMaterial';
$route['update-material'] = 'Admin/updateMaterial';
$route['delete-material'] = 'Admin/deleteMaterial';

// get software
$route['requerment'] = 'Software/index';
$route['get-software'] = 'Software/getSoftware';
$route['add-software'] = 'Software/addSoftware';
$route['update-software'] = 'Software/updateSoftware';
$route['delete-software'] = 'Software/deletedSoftware';

// post
$route['post'] = 'Employee/post';
$route['get-posts'] = 'Employee/getPosts';
$route['add-post'] = 'Employee/addPost';
$route['update-post'] = 'Employee/updatePost';
$route['delete-post'] = 'Employee/deletePost';

// Department
$route['department'] = 'Employee/department';
$route['get-departments'] = 'Employee/getDepartments';
$route['add-department'] = 'Employee/addDepartment';
$route['update-department'] = 'Employee/updateDepartment';
$route['delete-department'] = 'Employee/deleteDepartment';

// employees
$route['employee'] = 'Employee/employee';
$route['get-employees'] = 'Employee/getEmployee';
$route['get-all-employees'] = 'Employee/getAllEmployees';
$route['generate-employee-code'] = 'Employee/generateEmployeeCode';
$route['add-employee'] = 'Employee/addEmployee';
$route['update-employee'] = 'Employee/updateEmployee';
$route['delete-employee'] = 'Employee/deleteEmployee';
$route['active-employee'] = 'Employee/activeEmployee';

// task type
$route['type'] = 'Task/type';
$route['get-types'] = 'Task/getTypes';
$route['add-type'] = 'Task/addType';
$route['update-type'] = 'Task/updateType';
$route['delete-type'] = 'Task/deleteType';

// task
$route['task'] = 'Task/index';
$route['get-tasks'] = 'Task/getTasks';
$route['add-task'] = 'Task/addTask';
$route['update-task'] = 'Task/updateTask';
$route['delete-task'] = 'Task/deleteTask';
$route['task-record'] = 'Task/taskRecord';
$route['change-task-status'] = 'Task/changeTaskStatus';
$route['my-task'] = 'Task/myTaskRecord';
$route['complete-task'] = 'Task/completeTaskRecord';
$route['archive-task'] = 'Task/archiveTaskRecord';
$route['update-task-status'] = 'Task/updateTaskStatus';
$route['task_invoice_print/(:any)'] = 'Task/taskInvoicePrint/$1';
$route['project_visit_invoice_print/(:any)'] = 'Task/projectVisitInvoicePrint/$1';
$route['vendor_bill_invoice_print/(:any)'] = 'Task/vendorBillInvoicePrint/$1';
$route['project_bill_invoice_print/(:any)'] = 'Task/projectBillInvoicePrint/$1';
// $route['task_invoice_print_only/(:any)'] = 'Task/taskInvoicePrint/$1';

// Project Entry
$route['project_entry'] = 'Task/projectEntry';
$route['add-project'] = 'Task/addProject';
$route['get-projects'] = 'Task/getProjects';
$route['get-projects-by-projectid'] = 'Task/getProjectByProjectId';
$route['get-projectType-by-projectid'] = 'Task/getProjectTypeByProjectId';
$route['update-project'] = 'Task/updateProject';
$route['delete-project'] = 'Task/deleteProject';

// Accounts Module
$route['get_cash_transactions'] = 'Account/getCashTransactions';
$route['cashTransaction'] = 'Account/cash_transaction';
$route['get_cash_transaction_code'] = 'Account/getCashTransactionCode';
$route['get_vendor_expense_transaction_code'] = 'Account/getVendorExpenseTransactioncode';
$route['add_cash_transaction'] = 'Account/addCashTransaction';
$route['update_cash_transaction'] = 'Account/updateCashTransaction';
$route['delete_cash_transaction'] = 'Account/deleteCashTransaction';
$route['transactionEdit'] = 'Account/cash_transaction_edit';
$route['viewTransaction/(:any)'] = 'Account/viewTransaction/$1';
$route['vendor_expense'] = 'Account/vendorExpense';
$route['add_vendor_expense'] = 'Account/addVendorExpense';
$route['get_vendor_expense'] = 'Account/getVendorExpense';
$route['update_vendor_expense'] = 'Account/updateVendorExpense';
$route['get_all_vendor_expense_transactions'] = 'Account/getAllVendorExpenseTransactions';

// Vendor bill Entry
$route['vendor_bill_entry'] = 'Account/vendorBillEntry';
$route['add_vendor_bill_entry'] = 'Account/addVendorBillEntry';
$route['get_vendor_bill'] = 'Account/getVendorBill';
$route['update_vendor_bill_entry'] = 'Account/updateVendorBillEntry';
$route['delete_vendor_bill_entry'] = 'Account/deleteVendorBillEntry';
$route['vendor_ledger'] = 'Account/vendorLedger';
$route['get_vendor_ledger'] = 'Account/getVendorLedger';
$route['vendor_bill_record'] = 'Account/vendorBillRecord';

// Project Bill Entry
$route['get_project_bill_code'] = 'Account/getProjectBillCode';
$route['project_bill_entry'] = 'Account/projectBillEntry';
$route['add_project_bill_entry'] = 'Account/addProjectBillEntry';
$route['update_project_bill_entry'] = 'Account/updateProjectBillEntry';
$route['get_project_bill_entry'] = 'Account/getProjectBillEntry';
$route['project_ledger'] = 'Account/projectLedger';
$route['client_ledger'] = 'Account/clientLedger';
$route['monthly_revenue_report'] = 'Account/monthlyRevenueReport';
$route['get_monthly_revenue_report'] = 'Account/getMonthlyRevenueReport';
$route['project_bill_record'] = 'Account/projectBillRecord';

$route['get_project_ledger'] = 'Account/getProjectLedger';
$route['get_client_ledger'] = 'Account/getClientLedger';

// Project Visit

// $route['add_project_visit'] = 'Project/proje'
// Client Transaction
$route['project_transactions'] = 'Account/project_transaction';
$route['get_client_current_balance'] = '/Account/getClientCurrentBalance';
$route['add_client_transaction'] = '/Account/addClientTransaction';
$route['update_client_transaction'] = '/Account/updateClientTransaction';
$route['get_client_transactions'] = '/Account/getClientTransactions';
$route['project_transactions_report'] = '/Account/projectTransactionsReport';
$route['vendor_expense_report'] = '/Account/vendorExpenseReport';
$route['remove_client_transaction'] = '/Account/removeClientTransaction';
$route['remove_project_expense'] = '/Account/removeProjectExpense';

// $route['get_bank_balance'] = '/Account/getBankBalance';

// Project Expense
$route['project_expense'] = 'Account/project_expense';
$route['get_project_type'] = 'Account/getProjectType';
$route['add_project_expense'] = 'Account/addProjectExpense';
$route['get_project_expense'] = 'Account/getProjectExpense';
$route['update_project_expense'] = 'Account/updateProjectExpense';
$route['project_expense_report'] = '/Account/projectExpenseReport';

// Project Visit
$route['project_visit'] = 'Project/project_visit';
$route['add_project_visit'] = 'Project/addProjectVisit';
$route['get_project_visit'] = 'Project/getProjectVisit';
$route['project_visit_record'] = 'Project/projectVisitRecord';
$route['update_project_visit'] = 'Project/updateProjectVisit';
$route['delete_project_visit'] = 'Project/deleteProjectVisit';

// Project Matarial Estimate
$route['project_matarial_estimate'] = 'Project/projectMatarialEstimate';
$route['add_project_material_estimate'] = 'Project/addProjectMaterialEstimate';
$route['get_project_material_estimate'] = 'Project/getProjectMaterialEstimate';
$route['update_project_material_estimate'] = 'Project/updateProjectMaterialEstimate';
$route['delete_material_estimate'] = 'Project/deleteMaterialEstimate';
$route['project_matarial_estimate_record'] = 'Project/projectMatarialEstimateRecord';

// Work Estimate Sheet Entry
$route['work_estimate_sheet_entry'] = 'Project/workEstimateSheetEntry';
$route['add_work_estimate'] = 'Project/addWorkEstimate';
$route['get_work_estimate'] = 'Project/getWorkEstimate';
$route['update_work_estimate'] = 'Project/updateWorkEstimate';
$route['delete_work_estimate'] = 'Project/deleteWorkEstimate';
$route['work_estimate_sheet_record'] = 'Project/workEstimateSheetRecord';

// Customer Payment Page
$route['customerPaymentPage'] = 'Client/customerPaymentPage';
$route['customer_payment_history'] = 'Client/customerPaymentHistory';

// Accounts Ledger
$route['TransactionReport'] = 'Account/all_transaction_report';
$route['TransactionReportSearch'] = 'Account/transaction_report_search';
$route['transactionReportPrint'] = 'Reports/transaction_report_print';
$route['bank_transaction_report'] = 'Account/bankTransactionReprot';

$route['get_all_client_transactions'] = '/Account/getAllClientTransactions';
$route['get_all_project_expense'] = '/Account/getAllProjectExpense';
$route['monthly_report'] = '/Account/monthlyReport';

$route['account'] = 'Account';
$route['add_account'] = 'Account/addAccount';
$route['accountEdit'] = 'Account/account_edit';
$route['update_account'] = 'Account/updateAccount';
$route['delete_account'] = 'Account/deleteAccount';
$route['get_accounts'] = 'Account/getAccounts';
$route['get_cash_and_bank_balance'] = 'Account/getCashAndBankBalance';

// Monthly Report

$route['get_client_count'] = 'Account/getClientCount';
$route['get_review_client_count'] = 'Account/getReviewClientCount';
$route['get_no_review_client_count'] = 'Account/getNoReviewClientCount';
$route['project_count'] = 'Account/projectCount';
$route['project_order_value'] = 'Account/projectOrderValue';
$route['cash_ledger'] = 'Account/cashLedger';
$route['get_cash_ledger'] = 'Account/getCashLedger';

// Cash View
$route['cash_view'] = 'Account/cashView';
$route['bank_ledger'] = 'Account/bankLedger';

// Bank Transactions
$route['bank_transactions'] = '/Account/bankTransactions';
$route['add_bank_transaction'] = '/Account/addBankTransaction';
$route['update_bank_transaction'] = '/Account/updateBankTransaction';
$route['get_bank_transactions'] = '/Account/getBankTransactions';
$route['get_all_bank_transactions'] = '/Account/getAllBankTransactions';
$route['remove_bank_transaction'] = '/Account/removeBankTransaction';
$route['get_bank_balance'] = '/Account/getBankBalance';

// Banks
$route['bank_accounts'] = 'Account/bankAccounts';
$route['add_bank_account'] = 'Account/addBankAccount';
$route['update_bank_account'] = 'Account/updateBankAccount';
$route['get_bank_accounts'] = 'Account/getBankAccounts';
$route['change_account_status'] = 'Account/changeAccountStatus';

// SMS
$route['sms'] = 'SMS';
$route['send_sms'] = 'SMS/sendSms';
$route['send_bulk_sms'] = 'SMS/sendBulkSms';
$route['sms_settings'] = 'SMS/smsSettings';
$route['get_sms_settings'] = 'SMS/getSmsSettings';
$route['save_sms_settings'] = 'SMS/saveSmsSettings';

$route['404_override'] = '';
$route['translate_uri_dashes'] = true;
