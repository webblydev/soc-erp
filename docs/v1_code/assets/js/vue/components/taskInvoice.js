const salesInvoice = Vue.component("sales-invoice", {
	template: `
        <div>
            <div class="row">
                <div class="col-xs-12">
                    <a href="" v-on:click.prevent="print"><i class="fa fa-print"></i> Print</a>
                </div>
            </div>
            
            <div id="invoiceContent">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <div _h098asdh>
                            Task Invoice
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-7">
                        <strong>Client Id:</strong> {{ task.client_id }}<br>
						<strong>Client Name:</strong> {{ task.client_name }}<br>
                        <strong>Client Phone:</strong> {{ task.client_phone }}<br>
                        <strong>Project Name:</strong> {{ task.project_name }}<br>
						<strong>Status:</strong> {{ task.status_text }}<br>
						<strong> Entry Date:</strong> {{ task.entry_date }} {{ task.AddTime | formatDateTime('h:mm a') }}
                    </div>
                    <div class="col-xs-5 text-left">   
                        <strong>Support Person</strong> {{ task.support_person }}<br>
						<strong>File No:</strong> {{ task.file_number }}<br>
						<strong>Bill No:</strong> {{ task.bill_number }}<br>
                        <strong>Assigned_Person :</strong> {{ task.assigned_person }}<br>
                        <strong> Deadline:</strong> {{ task.deadline }} {{ task.AddTime | formatDateTime('h:mm a') }} <br>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12">
                        <div _d9283dsc></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12">
                        <table _a584de>
                            <thead>
                                <tr>
                                    <td>Service Name</td>
									  <td>Description</td>
                                    <td>Bill Amount</td>
                                    <td>Collect Amount</td>
									 <td>Due Amount</td>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ task.type }}</td>
									<td> {{ task.task_detail }}</td>
                                    <td>{{ task.bill_amount }}</td>
                                    <td>{{ task.collect_amount }}</td>
                                    <td align="right">{{ task.due_amount }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row" style="display:none">
                    <div class="col-xs-12">
                        <strong>In Word: </strong> {{ convertNumberToWords(task.bill_amount) }}<br><br>
                        <strong>Note: </strong>
                        <p style="white-space: pre-line">{{ task.task_detail }}</p>
                    </div>
                </div>

				<div class="row" style="margin-top:30px;"> 
					<div class="col-xs-3 text-center"> 
						<h6 style="margin-bottom:2px">Prepard By</h6>
						<strong> Marketing & Sales</strong>
					</div>

					<div class="col-xs-3 text-center"> 
						<h6 style="margin-bottom:2px">Checked By</h6>
						<strong> Task Superviser</strong>
					</div>

					<div class="col-xs-3 text-center"> 
						<h6 style="margin-bottom:2px">Forwaded By</h6>
						<strong> Account & Finance </strong>
					</div>

					<div class="col-xs-3 text-center"> 
						<h6 style="margin-bottom:2px"> Approved By </h6>
						<strong> Managing Director </strong>
					</div>
				</div>

				<div class="row" style="display:none" :style="{display: task.status_text != 'Pending' ? '' : 'none' }"> 
					<div class="col-xs-12" style="text-align:center"> 
						 <h4 style="margin-bottom:2px;"> INTERNAL DEPARTMENTAL WORK ORDER </h4>
						<h6 style="margin-bottom:2px;"> The terms of the agreements, which were executed by both parties (Sales Dept & Assigned Person) </h6>
						<table border="1px" style="width: 100%; margin-top:2px;"> 
							<tr style="width:100%"> 
								<td style="width:15%">Task File No</td>
								<td style="width:20%"> {{task.file_number}} </td>
								<td style="width:15%"> Entry Date</td>
								<td style="width:20%;">{{task.entry_date}}</td>
								<td style="width:15%;"> Superviser</td>
								<td style="width:15%"> {{task.support_person}} </td>
							</tr>

							<tr style="width:100%"> 
								<td style="width:20%">Assigned Person</td>
								<td style="width:20%"> {{task.assigned_person}}</td>
								<td style="width:20%;">Designation</td>
								<td style="width: 20%"></td>
								<td style="width: 20%" colspan="2">Id:______________</td>
							</tr>

							<tr style="width: 100%"> 
								<td style="width:20%">Work Starting Date: </td>
								<td style="width:30%"> {{task.entry_date}} </td>
								<td style="width:20%">Work Completed Date</td>
								<td style="width: 0%" colspan="3">{{task.completed_date}}</td>
							</tr>
							<tr style="width: 100%"> 
								<td style="width:100%; text-align:left;" colspan="6">
									1) The said work order will be considered as the main document of distribution and assumption of responsibilities as per 
									the project. <br>
									2) The person taking responsibility must fulfill the responsibility in the interest of the organization, in case of any 
									irregularity,the person taking responsibility will be responsible  <br>
									3) In case of any irregularity /failure to protect the interest of the organization by the person taking responsibility, the 
									organization may take alI necessary steps. <br>
									4) Responsible, will take responsibility for the good/bad of the partial or complete work done. It is the location of 
									working in the organization or not working in the organization.
								</td>
							</tr>
				
						</table>
					</div>

				</div>

				<div class="row" style="margin-top:15px; display:none" :style="{display: task.status_text != 'Pending' ? '' : 'none' }"> 
					<div class="col-xs-4"> 
						<table border="1px" style="width: 90%; margin: 0 2.5%; text-align:center; font-weight:bold;"> 
							<tr style="width: 100%;"> 
								<td style="width: 35%"> 
									Executive
								</td>
								<td style="width: 65%"> 
									Team Leader / Heade of Project
								</td>
							</tr>
							<tr> 
								<td></td>
								<td></td>
							</tr>

							<tr style="width: 100%"> 
							  <td colspan="2" style="width: 100%">
							 	Work Order Finalist  Signature of the Person Giving the Reponsibility 
							  </td>
							</tr>
						</table>					
					</div>

					<div class="col-xs-8"> 
						<table border="1px" style="width: 97.5%; margin-left:2.5%; text-align:center; font-weight:bold;"> 
							<tr style="width: 100%;"> 
								<td style="width: 20%"> 
									Executive
								</td>
								<td style="width: 30%"> 
									Team Leader / Heade of Project
								</td>
								<td style="width: 50%;" rowspan="2"> 
									<input style="border:none;" type="textarea" colspan="5" rowspan="35"/>
								</td>
							</tr>
							<tr> 
								<td></td>
								<td></td>
								<td></td>
							</tr>

							<tr style="width: 100%"> 
							  <td style="width: 50%">
							 	Work Order Finalist  Signature of the Person Giving the Reponsibility 
							  </td>

							  <td style="width: 50%"> 
							  	Final Approved of the aggrement behalf of Management
							  </td>
							</tr>
						</table>					
					</div>
			
				</div>
            </div>
        </div>
    `,
	props: ["task_id"],
	data() {
		return {
			sales: {
				SaleMaster_InvoiceNo: null,
				SalseCustomer_IDNo: null,
				SaleMaster_SaleDate: null,
				Customer_Name: null,
				Customer_Address: null,
				Customer_Mobile: null,
				SaleMaster_TotalSaleAmount: null,
				SaleMaster_TotalDiscountAmount: null,
				SaleMaster_TaxAmount: null,
				SaleMaster_Freight: null,
				SaleMaster_SubTotalAmount: null,
				SaleMaster_PaidAmount: null,
				SaleMaster_DueAmount: null,
				SaleMaster_Previous_Due: null,
				SaleMaster_Description: null,
				AddBy: null,
			},
			cart: [],
			task: [],
			style: null,
			companyProfile: null,
			currentBranch: null,
		};
	},
	filters: {
		formatDateTime(dt, format) {
			return dt == "" || dt == null ? "" : moment(dt).format(format);
		},
	},
	created() {
		this.setStyle();
		this.getSales();
	},
	methods: {
		getSales() {
			axios.post("/get-tasks", { taskId: this.task_id }).then((res) => {
				this.task = res.data[0];
				this.cart = res.data.saleDetails;
			});
		},

		setStyle() {
			this.style = document.createElement("style");
			this.style.innerHTML = `
                div[_h098asdh]{
                    /*background-color:#e0e0e0;*/
                    font-weight: bold;
                    font-size:15px;
                    margin-bottom:15px;
                    padding: 5px;
                    border-top: 1px dotted #454545;
                    border-bottom: 1px dotted #454545;
                }
                div[_d9283dsc]{
                    padding-bottom:25px;
                    border-bottom: 1px solid #ccc;
                    margin-bottom: 15px;
                }
                table[_a584de]{
                    width: 100%;
                    text-align:center;
                }
                table[_a584de] thead{
                    font-weight:bold;
                }
                table[_a584de] td{
                    padding: 3px;
                    border: 1px solid #ccc;
                }
                table[_t92sadbc2]{
                    width: 100%;
                }
                table[_t92sadbc2] td{
                    padding: 2px;
                }
            `;
			document.head.appendChild(this.style);
		},
		convertNumberToWords(amountToWord) {
			var words = new Array();
			words[0] = "";
			words[1] = "One";
			words[2] = "Two";
			words[3] = "Three";
			words[4] = "Four";
			words[5] = "Five";
			words[6] = "Six";
			words[7] = "Seven";
			words[8] = "Eight";
			words[9] = "Nine";
			words[10] = "Ten";
			words[11] = "Eleven";
			words[12] = "Twelve";
			words[13] = "Thirteen";
			words[14] = "Fourteen";
			words[15] = "Fifteen";
			words[16] = "Sixteen";
			words[17] = "Seventeen";
			words[18] = "Eighteen";
			words[19] = "Nineteen";
			words[20] = "Twenty";
			words[30] = "Thirty";
			words[40] = "Forty";
			words[50] = "Fifty";
			words[60] = "Sixty";
			words[70] = "Seventy";
			words[80] = "Eighty";
			words[90] = "Ninety";
			amount = amountToWord == null ? "0.00" : amountToWord.toString();
			var atemp = amount.split(".");
			var number = atemp[0].split(",").join("");
			var n_length = number.length;
			var words_string = "";
			if (n_length <= 9) {
				var n_array = new Array(0, 0, 0, 0, 0, 0, 0, 0, 0);
				var received_n_array = new Array();
				for (var i = 0; i < n_length; i++) {
					received_n_array[i] = number.substr(i, 1);
				}
				for (var i = 9 - n_length, j = 0; i < 9; i++, j++) {
					n_array[i] = received_n_array[j];
				}
				for (var i = 0, j = 1; i < 9; i++, j++) {
					if (i == 0 || i == 2 || i == 4 || i == 7) {
						if (n_array[i] == 1) {
							n_array[j] = 10 + parseInt(n_array[j]);
							n_array[i] = 0;
						}
					}
				}
				value = "";
				for (var i = 0; i < 9; i++) {
					if (i == 0 || i == 2 || i == 4 || i == 7) {
						value = n_array[i] * 10;
					} else {
						value = n_array[i];
					}
					if (value != 0) {
						words_string += words[value] + " ";
					}
					if (
						(i == 1 && value != 0) ||
						(i == 0 && value != 0 && n_array[i + 1] == 0)
					) {
						words_string += "Crores ";
					}
					if (
						(i == 3 && value != 0) ||
						(i == 2 && value != 0 && n_array[i + 1] == 0)
					) {
						words_string += "Lakhs ";
					}
					if (
						(i == 5 && value != 0) ||
						(i == 4 && value != 0 && n_array[i + 1] == 0)
					) {
						words_string += "Thousand ";
					}
					if (
						i == 6 &&
						value != 0 &&
						n_array[i + 1] != 0 &&
						n_array[i + 2] != 0
					) {
						words_string += "Hundred and ";
					} else if (i == 6 && value != 0) {
						words_string += "Hundred ";
					}
				}
				words_string = words_string.split("  ").join(" ");
			}
			return words_string + " only";
		},
		async print() {
			let invoiceContent = document.querySelector("#invoiceContent").innerHTML;
			let printWindow = window.open(
				"",
				"PRINT",
				`width=${screen.width}, height=${screen.height}, left=0, top=0`
			);

			printWindow.document.write(`
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta http-equiv="X-UA-Compatible" content="ie=edge">
                        <title>Invoice</title>
                        <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
                        <style>
                            body, table{
                                font-size: 13px;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <table style="width:100%;">
                                <thead>
                                    <tr>
                                        <td>
                                            <div class="row" style="text-align:center;">
                                                <div class="col-xs-2"> 
													<img src="/uploads/company_profile_thum/logo.png" alt="Logo" style="width: 120px; height:auto;margin:0px;" />
												</div>
                                				<div class="col-xs-10"> 
													<strong style="font-size:18px;">SOC Consultant & Development Ltd
													</strong><br>
                                					<p style="white-space:pre-line;">1086,  Shabajpur, Gulshan,Dhaka </p>
												</div>
                           					 </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-12">
                                                    <div style="border-bottom: 4px double #454545;margin-top:7px;margin-bottom:7px;"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row">
                                                <div class="col-xs-12">
                                                    ${invoiceContent}
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>
                                            <div style="width:100%;height:50px;">&nbsp;</div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>

                        </div>
                        
                    </body>
                    </html>
				`);

			let invoiceStyle = printWindow.document.createElement("style");
			invoiceStyle.innerHTML = this.style.innerHTML;
			printWindow.document.head.appendChild(invoiceStyle);
			printWindow.moveTo(0, 0);

			printWindow.focus();
			await new Promise((resolve) => setTimeout(resolve, 1000));
			printWindow.print();
			await new Promise((resolve) => setTimeout(resolve, 1000));
			printWindow.close();
		},
	},
});
