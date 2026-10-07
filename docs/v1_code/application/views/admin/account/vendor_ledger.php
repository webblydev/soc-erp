<style>
    .v-select {
        margin-bottom: 5px;
        float: right;
        min-width: 150px;
        margin-left: 5px;
    }

    .v-select .dropdown-toggle {
        padding: 0px;
        height: 23px;
    }

    .v-select input[type=search],
    .v-select input[type=search]:focus {
        margin: 0px;
    }

    .v-select .vs__selected-options {
        overflow: hidden;
        flex-wrap: nowrap;
    }

    .v-select .selected-tag {
        margin: 2px 0px;
        white-space: nowrap;
        position: absolute;
        left: 0px;
    }

    .v-select .vs__actions {
        margin-top: -5px;
    }

    .v-select .dropdown-menu {
        width: auto;
        overflow-y: auto;
    }

    #bankTransactionReport label {
        font-size: 13px;
    }

    #bankTransactionReport select {
        border-radius: 3px;
        padding: 0px;
    }

    #bankTransactionReport .form-group {
        margin-right: 5px;
    }

    #bankTransactionReport .search-button {
        margin-top: -6px;
    }

    #transactionsTable th {
        text-align: center;
    }
</style>
<div id="bankTransactionReport">
    <div class="row" style="border-bottom: 1px solid #ccc;margin-bottom: 15px;">
        <div class="col-md-12">
            <form class="form-inline" @submit.prevent="getTransactions">

                <div class="form-group">
                    <label>Vendor</label>
                    <v-select v-bind:options="vendors" v-model="selectedVendor" label="name"
                        placeholder="Select Vendor" @input="getProject">
                    </v-select>
                </div>


                <div class="form-group" style="display: none" :style="{display: projects.length> 0 ? '' : 'none'}">
                    <label>Project</label>
                    <v-select v-bind:options="projects" v-model="selectedProject" label="name"
                        placeholder="Select Project">
                    </v-select>
                </div>

                <div class="form-group">
                    <label> Date From </label>
                    <input type="date" class="form-control" v-model="filter.dateFrom" @change="resetData">
                </div>

                <div class="form-group">
                    <label>to</label>
                    <input type="date" class="form-control" v-model="filter.dateTo" @change="resetData">
                </div>

                <div class="form-group">
                    <input type="submit" value="search" class="search-button">
                </div>
            </form>
        </div>
    </div>

    <div class="row" style="display:none;" v-bind:style="{display: showTable ? '' : 'none'}">
        <div class="col-md-12" style="margin-top:15px;margin-bottom:15px;">
            <a href="" @click.prevent="print"><i class="fa fa-print"></i> Print</a>
        </div>
        <div class="col-md-12">
            <div class="table-responsive" id="reportTable">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="4" style="text-align:center">Bill Details</th>
                            <th colspan="7" style="text-align:center">Payment Details</th>
                        </tr>
                        <tr>
                            <th style="text-align:center">Date</th>
                            <th style="text-align:center">Work Order No.</th>
                            <th style="text-align:center">Name Of Project</th>
                            <th style="text-align:center">bill No.</th>
                            <th style="text-align:center">Voucher No.</th>
                            <th style="text-align:center">Cash/Cheque</th>
                            <th style="text-align:center">Bill Amount</th>
                            <th style="text-align:center">Total Bill Amount</th>
                            <th style="text-align:center">Paid Amount</th>
                            <th style="text-align:center">Total Paid Amount</th>
                            <th style="text-align:center">Due Amount</th>

                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                            <td colspan="6" style="text-align:left;">Previous Balance</td>

                            <td style="text-align:right;">{{ parseFloat(total_Bill).toFixed(2) }}</td>
                            <td style="text-align:right;"></td>
                            <td style="text-align:right;">{{ parseFloat(total_Paid).toFixed(2) }}</td>
                            <td style="text-align:right;">{{ parseFloat(bill_amount).toFixed(2) }}</td>
                        </tr>
                        <tr v-for="row in ledger">
                            <td>{{ row.date }}</td>
                            <td style="text-align:left;">{{ row.tr_no }}</td>
                            <td style="text-align:left;">{{ row.project_name }}</td>
                            <td style="text-align:left;">{{ row.bill_no }}</td>
                            <td style="text-align:left;">{{ row.slip_no }}</td>
                            <td style="text-align:left;"></td>
                            <td style="text-align:left;">{{ parseFloat(row.in_amount).toFixed(2) }}</td>
                            <td style="text-align:left;">{{ parseFloat(row.totalBillAmount).toFixed(2) }}</td>
                            <td style="text-align:left;">{{ parseFloat(row.out_amount).toFixed(2) }}</td>
                            <td style="text-align:left;">{{ parseFloat(row.totalPaidAmount).toFixed(2) }}</td>
                            <td style="text-align:left;">{{ parseFloat(row.balance).toFixed(2) }}</td>

                        </tr>
                    </tbody>
                    <tbody v-if="ledger.length == 0">
                        <tr>
                            <td colspan="5">No records found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
    Vue.component('v-select', VueSelect.VueSelect);
    new Vue({
        el: '#bankTransactionReport',
        data() {
            return {
                transactions: [],
                ledger: [],
                selectedVendor: null,
                vendors: [],
                selectedProject: null,
                projects: [],
                filter: {
                    transactionType: '',
                    dateFrom: moment().format('YYYY-MM-DD'),
                    dateTo: moment().format('YYYY-MM-DD')
                },
                bill_amount: 0.00,
                total_Bill: 0.00,
                total_Paid: 0.00,
                showTable: false
            }
        },

        created() {
            this.getVendors();
            this.getProject();
        },
        methods: {
            getVendors() {
                axios.get('/get-vendors')
                    .then(res => {
                        this.vendors = res.data;
                    });
            },

            getProject() {
                axios.get('/get-projects')
                    .then(res => {
                        this.projects = res.data;
                    });
            },
            resetData() {
                this.transactions = [];
            },

            getTransactions() {
                let data = {
                    dateFrom: this.filter.dateFrom,
                    dateTo: this.filter.dateTo,
                    vendorId: this.selectedVendor?.id || null,
                    projectId: this.selectedProject?.id || null,
                };
                axios.post('/get_vendor_ledger', data)
                    .then(res => {
                        this.ledger = res.data.ledger;
                        this.bill_amount = res.data.bill_amount;
                        this.total_Bill = res.data.totalBill;
                        this.total_Paid = res.data.totalPaid;
                        this.showTable = true;
                    });
            },

            async print() {
                const printElement = document.querySelector('#reportTable');

                if (!printElement) {
                    console.error("Element with ID 'reportTable' not found.");
                    return; 
                }

                let dateText = "";
                if (this.filter.dateFrom && this.filter.dateTo) {
                    dateText = `Statement from <strong>${this.filter.dateFrom}</strong> to <strong>${this.filter.dateTo}</strong>`;
                }

                let reportTable = `
                        <style>
                            table {
                                width: 100%;
                                border-collapse: collapse;
                            }
                            th, td {
                                border: 1px solid black;
                                padding: 8px;
                                text-align: left;
                            }
                            th {
                                background-color: #f2f2f2; /* Optional: Background color for headers */
                            }
                        </style>
                        <div class="container">
                            <h4 style="text-align:center">Cash Transaction Report</h4>
                            <div class="row">
                                <div class="col-xs-6 col-xs-offset-6 text-right">
                                    ${dateText}
                                </div>
                            </div>
                        </div>
                        <div class="container">
                            <div class="row">
                                <div class="col-xs-12">
                                    ${printElement.innerHTML}
                                </div>
                            </div>
                        </div>
                    `;

                let printWindow = window.open('', '', `width=${screen.width}, height=${screen.height}`);
                printWindow.document.write('<html><head><title>Print</title></head><body>');
                printWindow.document.write(reportTable);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();
                await new Promise(r => setTimeout(r, 500));
                printWindow.print();
                printWindow.close();
            }


        }
    });
</script>