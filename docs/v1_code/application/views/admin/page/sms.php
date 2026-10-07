
<div class="main-content" id="sms">
	<div class="container main-content-inner">
		<div class="breadcrumbs ace-save-state" id="breadcrumbs">
			<ul class="breadcrumb">
				<li>
					<i class="ace-icon fa fa-home home-icon"></i>
					<a href="#">Home</a>
				</li>
				<li class="active">Client Entry</li>
			</ul>

			<div class="nav-search" id="nav-search">
				<span style="font-weight: bold; color: #972366; font-size: 16px;">
					<i class="ace-icon fa fa-home home-icon"></i>
				</span>
			</div><!-- /.nav-search -->
		</div>
		
			<div class="row">
				<div class="col-xs-12 col-sm-5 col-md-12 col-lg-5">
                    <form v-on:submit.prevent="sendSms">
                        <div class="form-group">
                            <label for="smsText">SMS Text</label>
                            <textarea class="form-control" id="smsText" v-model="smsText" v-on:input="checkSmsLength" style="height:100px !important;"></textarea>
                            <p style="display:none" v-bind:style="{display: smsText.length > 0 ? '' : 'none'}">{{ smsText.length }} | {{ smsLength - smsText.length }} Remains | Max: {{ smsLength }} characters</p>
                        </div>
                        <div class="form-group">
                            <input type="radio" name="type" value="p" v-model="type" v-on:change="getCustomers"> Knock Client &nbsp;
							<input type="radio" name="type" value="s" v-model="type" v-on:change="getCustomers"> Sold Client
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-xs pull-right" v-bind:style="{display: onProgress ? 'none' : ''}"> <i class="fa fa-send"></i> Send </button>
                            <button type="button" class="btn btn-primary btn-xs pull-right" disabled style="display:none" v-bind:style="{display: onProgress ? '' : 'none'}"> Please Wait .. </button>
                        </div>
                    </form>
				</div><!-- /.col -->
			</div><!-- /.row -->

            <!-- send message -->
            <div class="row" style="margin-top: 25px;">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Select All &nbsp; <input type="checkbox" v-on:click="selectAll"></th>
                                    <th>Client Name</th>
                                    <th>Organization Name</th>
                                    <th>Mobile</th>
                                    <th>Area</th>
                                </tr>
                            </thead>
                            <tbody style="display:none" v-bind:style="{display: customers.length > 0 ? '' : 'none'}">
                                <tr v-for="customer in customers">
                                    <td><input type="checkbox" v-bind:value="customer.phone" v-model="selectedCustomers" v-if="customer.phone.match(regexMobile)"></td>
                                    <td>{{ customer.client_name }}</td>
                                    <td>{{ customer.org_name }}</td>
                                    <td><span class="label label-md arrowed" v-bind:class="[customer.phone.match(regexMobile) ? 'label-info' : 'label-danger']">{{ customer.phone }}</span></td>
                                    <td>{{ customer.name }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- send message -->

		<div class="page-content">

			<div id="loader" hidden style="position: fixed; z-index: 1000; margin: auto; height: 100%; width: 100%; background:rgba(255, 255, 255, 0.72);;">
				<img src="<?php echo base_url() ?>assets/loader.gif" style="top: 30%; left: 50%; opacity: 1; position: fixed;">
			</div>
		</div><!-- /.page-content -->
	</div>
</div><!-- /.main-content -->

<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>

<script>
    const app = new Vue({
        el:'#sms',
        data(){
            return {
                type: 'p',
                customers:[],
                selectedCustomers: [],
                smsText: '',
                smsLength: 306,
                onProgress: false,
                regexMobile: /^01[13-9][\d]{8}$/
            }
        },
        created(){
            this.getCustomers();
        },
        methods:{
            getCustomers(){
                let filter = {
                    type: this.type,
                    userId: "<?php echo $this->session->userdata('userid')?>"
                }

                axios.post('/get-client', filter).then(res => {
                    this.customers = res.data.map(customer => {
                        customer.phone = customer.phone.trim();
                        return customer;
                    });
                })
            },
            selectAll(){
                let checked = event.target.checked;
                if(checked){
                    this.selectedCustomers = [...new Set(this.customers.map(v => v.phone))].filter(mobile => mobile.match(this.regexMobile));
                } else {
                    this.selectedCustomers = [];
                }
            },
            checkSmsLength(){
                if(this.smsText.length > this.smsLength){
                    this.smsText = this.smsText.substring(0, this.smsLength);
                }
            },
            sendSms(){
                if(this.selectedCustomers.length == 0){
                    alert('Select customer');
                    return;
                }

                if(this.smsText.length == 0){
                    alert('Enter sms text');
                    return;
                }

                let data = {
                    smsText: this.smsText,
                    numbers: this.selectedCustomers
                }

                this.onProgress = true;
                axios.post('/send_bulk_sms', data).then(res => {
                    let r = res.data;
                    alert(r.message);
                    this.onProgress = false;
                })
            }
        }
    })
</script>
<style type="text/css">

</style>
