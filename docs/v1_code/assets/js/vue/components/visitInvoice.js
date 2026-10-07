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
                            Project Visit Invoice
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-7">
					   <strong>Name of Customer:</strong> {{project_visit.permitee_name}}<br>
					   <strong>Project Name:</strong> {{project_visit.name }}<br>
					   <strong>Location:</strong> {{ project_visit.location }}<br>
					   <strong>Name of Constructor:</strong> {{project_visit.constructor_name}}<br>
					   <strong>Field Office Phone:</strong> {{ project_visit.field_office_phone }}<br>
                      
                    </div>
                    <div class="col-xs-5 text-left">   
                        <strong>Construction Site ID No :</strong> {{ project_visit.construction_id }}<br>
                        <strong>Project Eng Name :</strong> {{ project_visit.project_eng_name }}<br>
					
                    </div>
                </div>
				<hr>

                <div class="row">
                    <div class="col-xs-7">
					   <strong>Date of Inspection:</strong> {{project_visit.inspection_date}}<br>
					   <strong>Start Time of Inspection:</strong> {{project_visit.start_time }}<br>
					   <strong>End Time of Inspection:</strong> {{project_visit.end_time}}<br>
                      
                    </div>
                    <div class="col-xs-5 text-left">   
                      <strong>Type of Inspection:</strong> {{ project_visit.inspection_type_weekly == 1 ? 'Weekly' : 'Precipitation Event' }}<br>

                        <strong>Project Eng Name :</strong> {{ project_visit.project_eng_name }}<br>
					
                    </div>
                </div>
				<hr>
                <div class="row">
                    <div class="col-xs-12">
					   <strong>Description of Present phase of Costruction :</strong> {{project_visit.description}}<br>
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
                                    <td>Location of Finding</td>
									<td>Description of Finding</td>
                                    <td>Finding by</td>
                                    <td>Regarding Taking</td>
                                    <td>Authorized by</td>
                                   
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(product, sl) in project_visit.details">
									<td>{{ product.visit_location }}</td>
									<td style="text-align:right;">{{ product.visit_description }}</td>
									<td style="text-align:center;">{{ product.visit_finding }}</td>
									<td style="text-align:center;">
									<span v-if="product.visit_regarding === 'yes'">Yes</span>
									<span v-else-if="product.visit_regarding === 'no'">No</span>
									<span v-else-if="product.visit_regarding === 'no_application'">No Application</span>
									<span v-else>Unknown</span>
								</td>
								<td style="text-align:center;"></td>
								
							  </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

				<div class="row" style="margin-top:15px;"> 
					<table border="1px" style="width: 97%; margin: 0 1.5%; font-weight:bold;"> 
						<tr style="width: 100%;"> 
							<td style="width: 30%;padding:0px 10px"> 
								<h5>Remarks : </h5>
							</td>
							<td style="width: 40%;padding:0px 10px"> 
							   <h5>Name of Project Incharge :</h5>
							   <h5>Designetion :</h5>
							   <h5>Singnature with Date : </h5>
							</td>
							<td style="width: 30%;padding:0px 10px"> 
								<h5>Management Signatue With Rcomandation : </h5>
							</td>
						</tr>
						
						
					</table>	
				</div>
				<div class="row" style="margin-top:15px;"> 
				   <p style="margin-left:10px">Info : Internal Department- Logstic / Account / Design / POD / Marketing </p>	
				</div>
            </div>
        </div>
    `,
	props: ["visit_id"],
	data() {
		return {
			sales: {
				SaleMaster_InvoiceNo: null,
				AddBy: null,
			},
			cart: [],
			project_visit: [],
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
		this.getProjects();
		this.getProjectVisit();
	},
	methods: {

		
		getProjectVisit() {
			axios.post('/get_project_visit', {visit_id: this.visit_id})
            .then(res => {
                this.project_visit = res.data.project_visit[0]; 
				this.cart = res.data.projectDetails;
				// console.log(res.data);
				
				
            })
           
        },
		getProjects() {
            axios.post('/get-projects').then(res => {
                this.projects = res.data;
            })

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
