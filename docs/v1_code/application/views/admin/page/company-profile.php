<style>
    #companyProfiles input[type="file"] {
		display: none;
	}
	#companyProfiles .custom-file-upload {
		border: 1px solid #ccc;
		display: inline-block;
		padding: 5px 12px;
		cursor: pointer;
		margin-top: 5px;
		background-color: #298db4;
		border: none;
		color: white;
	}
	#companyProfiles .custom-file-upload:hover{
		background-color: #41add6;
	}

	#branchImage{
		height: 100%;
	}
	.other-image {
        width: 60px;
        float: left;
        margin-right: 10px;
    }
</style>
<div id="companyProfiles">
	<div class="row">
		<div class="col-md-12">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Company Information</h4>
                    <div class="widget-toolbar">
                        <a href="#" data-action="collapse">
                            <i class="ace-icon fa fa-chevron-up"></i>
                        </a>

                        <a href="#" data-action="close">
                            <i class="ace-icon fa fa-times"></i>
                        </a>
                    </div>
                </div>

                <div class="widget-body">
                    <div class="widget-main">
                        <form method="post" @submit.prevent="savecompanyProfile">
                            <div class="row">
							    <div class="col-xs-4 col-xs-offset-4">
                                    <div class="form-group clearfix">
                                        <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Company:</label>
                                        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                            <input type="text" class="form-control"  v-model="companyProfile.name" required>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Phone:</label>
                                        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                            <input type="text" class="form-control"  v-model="companyProfile.phone" required>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">E-mail:</label>
                                        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                            <input type="email" class="form-control"  v-model="companyProfile.email">
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Address:</label>
                                        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                            <textarea class="form-control" style="min-height: 80px;" v-model="companyProfile.address"></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Logo:</label>
                                        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                            <div class="form-group">
                                                <div style="width: 100px;height:100px;border: 1px solid #ccc;overflow:hidden;text-align:center;">
                                                    <img id="branchImage" v-if="imageUrl == '' || imageUrl == null" src="/assets/no_image.gif">
                                                    <img id="branchImage" v-if="imageUrl != '' && imageUrl != null" v-bind:src="imageUrl">
                                                </div>
                                                <div>
                                                    <label class="custom-file-upload">
                                                        <input type="file" @change="previewImage"/>
                                                        Select Image
                                                    </label>
                                                    <!-- <label style="color: red;">Aspect Ratio: 116:45</label> -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4"></label>
                                        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                            <input type="submit" value="Save" class="btn  btn-sm btn-block btn-primary">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
	</div><!-- /.row -->
</div><!-- /.main-content -->

<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>

<script>
	const app = new Vue({
		el: '#companyProfiles',
		data(){
			return {
				companyProfile: {
					id: null,
					name: '',
					phone: '',
					email: '',
					address: '',
				},
                selectedFile: null,
                imageUrl: '',
                image: ''
			}
		},

		created(){
			this.getcompanyProfile()
		},

		methods: {
            previewImage(){
				if(event.target.files.length > 0){
					this.selectedFile = event.target.files[0];
					this.imageUrl = URL.createObjectURL(this.selectedFile);
				} else {
					this.selectedFile = null;
					this.imageUrl = null;
				}
			},
			
			getcompanyProfile(){
                axios.post('/get-companyProfile')
                .then(res => {
					this.companyProfile = res.data;
                    this.imageUrl = '<?php echo base_url('assets/images/')?>' + this.companyProfile.image
				    this.selectedFile = this.companyProfile.image
				})
			},
			savecompanyProfile(){
                let fd = new FormData();
                fd.append('image', this.selectedFile);
				fd.append('data', JSON.stringify(this.companyProfile));

				axios.post('/update-profile', fd)
				.then(res=>{
					let r = res.data;
					if(r.success){
						alert(r.message);
						this.resetForm();
						this.getcompanyProfile();
						window.location.reload()
					}
					else{
						alert(r.message);
					}
				})
			},
		}
	})
</script>
<style type="text/css">
	.v-select{
		margin-bottom: 5px;
	}
	.v-select.open .dropdown-toggle{
		border-bottom: 1px solid #ccc;
	}
	.v-select .dropdown-toggle{
		padding: 0px;
		height: 28px;
	}
	.v-select input[type=search], .v-select input[type=search]:focus{
		margin: 0px;
	}
	.v-select .vs__selected-options{
		overflow: hidden;
		flex-wrap:nowrap;
	}
	.v-select .selected-tag{
		margin: 2px 1px;
		white-space: nowrap;
		/*position:absolute;
		left: 0px;*/
	}
	.v-select .vs__actions{
		margin-top:-5px;
	}
	.v-select .dropdown-menu{
		width: auto;
		overflow-y:auto;
	}
	.modal-header {
		padding: 15px;
		border-bottom: 1px solid #e5e5e5;
		background: #e5e5e5;
	}
	h5 {
		font-size: 22px;
	}
	.modal-dialog,
	.modal-content {
		height: 90%;
	}
	.modal-body {
		max-height: calc(100% - 120px);
		overflow-y: scroll;
	}
	.card-area {
		border: 1px solid #ccc;
		border-radius: 3px;
		padding: 5px 10px;
		margin-bottom: 5px;
	}
</style>
