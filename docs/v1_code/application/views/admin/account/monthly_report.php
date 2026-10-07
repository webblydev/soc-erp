<style>
.v-select {
    margin-top: -2.5px;
    float: right;
    min-width: 180px;
    margin-left: 5px;
}

.v-select .dropdown-toggle {
    padding: 0px;
    height: 25px;
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

#searchForm select {
    padding: 0;
    border-radius: 4px;
}

#searchForm .form-group {
    margin-right: 5px;
}

.section12 {
    height: 140px;
}

#searchForm * {
    font-size: 13px;
}
</style>
<div id="cashTransactionReport">
    <div class="row" style="border-bottom: 1px solid #ccc;padding: 3px 0;">
        <div class="col-md-12">
            <form class="form-inline" id="searchForm" @submit.prevent="getReport">

                <div class="form-group">
                    <label for="">Date</label>
                    <input type="date" class="form-control" v-model="filter.dateFrom">
                </div>

                <div class="form-group">
                    <input type="date" class="form-control" v-model="filter.dateTo">
                </div>

                <div class="form-group" style="margin-top: -5px;">
                    <input type="submit" value="Search">
                </div>

            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 col-xs-12">
            <div class="col-md-1"></div>
            <div class="col-md-10">
                <!-- Header Logo -->
                <div class="col-md-12" style="border-bottom: 1px solid #ccc;margin-bottom:10px">
                    <h1 style="text-align: center;color: darkslateblue;font-size: 40px;">Monthly Report
                    </h1>
                </div>

                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #009688">
                        <a href="">
                            <div class="logo">
                                <!-- <?php $countClient = $this->db->query('select * from tbl_client')->num_rows();
                                echo $countClient; ?> -->
                                {{ clientCount }}
                            </div>
                            <div class="textModule">
                                New Client Count
                            </div>
                        </a>
                    </div>
                </div>

                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #27b086;border: 1px solid #209a8f;">
                        <a href="">
                            <div class="logo">
                                {{ reviewClient }}
                            </div>
                            <div class="textModule">
                                Review Client Count
                            </div>
                        </a>
                    </div>
                </div>

                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #27b086;border: 1px solid #209a8f;">
                        <a href="">
                            <div class="logo">
                                {{ noReviewClient }}
                            </div>
                            <div class="textModule">
                                No Communication Client
                            </div>
                        </a>
                    </div>
                </div>


                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #FFEB3B;border: 1px solid #FFEB3B;">
                        <a href="">
                            <div class="logo">
                                {{ project_count }}
                            </div>
                            <div class="textModule">
                                New Project Count
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #F44336;border: 1px solid #F44336;">
                        <a href="">
                            <div class="logo" style="font-size: 20px;;">
                                {{ project_order_value }}
                            </div>
                            <div class="textModule" style="font-size: 15px;">
                                New Order Value Count
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div><!-- /.col -->
    </div><!-- /.row -->

</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
Vue.component('v-select', VueSelect.VueSelect);
new Vue({
    el: '#cashTransactionReport',
    data() {
        return {
            filter: {
                transactionType: '',
                accountId: null,
                // dateFrom: moment().format('YYYY-MM-DD'),
                // dateTo: moment().format('YYYY-MM-DD')
                dateFrom: '',
                dateTo: ''
            },
            accounts: [],
            clientCount: null,
            reviewClient: null,
            project_order_value: null,
            noReviewClient: null,
            project_count: null,
            selectedAccount: null,
            transactions: []
        }
    },
    created() {
        this.getMonthlyReport();
    },
    methods: {

        getReport() {
            this.getClientCount();
            this.reviewClientCount();
            this.noReviewClientCount();
            this.projectCount();
            this.projectOrderValue();
        },

        getMonthlyReport() {
            this.getClientCount();
            this.reviewClientCount();
            this.noReviewClientCount();
            this.projectCount();
            this.projectOrderValue();
        },

        getClientCount() {
            let filter = {
                date_from: this.filter.dateFrom,
                date_to: this.filter.dateTo
            }
            axios.post('/get_client_count', filter).then(res => {
                this.clientCount = res.data;
            })
        },

        reviewClientCount() {
            let filter = {
                date_from: this.filter.dateFrom,
                date_to: this.filter.dateTo
            }
            axios.post('/get_review_client_count', filter).then(res => {
                this.reviewClient = res.data;
            })
        },

        noReviewClientCount() {
            let filter = {
                date_from: this.filter.dateFrom,
                date_to: this.filter.dateTo
            }
            axios.post('/get_no_review_client_count', filter).then(res => {
                this.noReviewClient = res.data;
            })
        },

        projectCount() {
            let filter = {
                date_from: this.filter.dateFrom,
                date_to: this.filter.dateTo
            }
            axios.post('/project_count', filter).then(res => {
                this.project_count = res.data;
            })
        },

        projectOrderValue() {
            let filter = {
                date_from: this.filter.dateFrom,
                date_to: this.filter.dateTo
            }
            axios.post('/project_order_value', filter).then(res => {
                this.project_order_value = res.data;
            })
        },



        onChangeAccount() {
            if (this.selectedAccount == null || this.selectedAccount.Acc_SlNo == undefined) {
                this.filter.accountId = null;
                return;
            }
            this.filter.accountId = this.selectedAccount.Acc_SlNo;
        },
        getTransactions() {
            this.getClient();
        },

        async print() {
            let dateText = "";
            if (this.filter.dateFrom != null && this.filter.dateTo != null) {
                dateText =
                    `Statement from <strong>${this.filter.dateFrom}</strong>  to <strong>${this.filter.dateTo}</strong>`;
            }
            let printContent = `
                    <div class="container">
                        <h4 style="text-align:center">Cash Transaction Report</h4 style="text-align:center">
                        <div class="row">
                            <div class="col-xs-6 col-xs-offset-6 text-right">
                                ${dateText}
                            </div>
                        </div>
                    </div>
                    <div class="container">
						<div class="row">
							<div class="col-xs-12">
								${document.querySelector('#printContent').innerHTML}
							</div>
						</div>
                    </div>
                `;

            let printWindow = window.open('', '', `width=${screen.width}, height=${screen.height}`);
            printWindow.document.write(`
                    <?php

                     $this->load->view('admin/reports/reportHeader.php');
                                ?>
                `);

            printWindow.document.body.innerHTML += printContent;
            printWindow.focus();
            await new Promise(r => setTimeout(r, 1000));
            printWindow.print();
            await new Promise(resolve => setTimeout(resolve, 1000));
            printWindow.close();
        }

    }
})
</script>
