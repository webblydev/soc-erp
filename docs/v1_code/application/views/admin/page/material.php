
<div id="materials">
	<div class="row">
		<div class="col-md-4 col-md-offset-4 col-xs-12">
			<!-- Client entry-->
			<form method="post" @submit.prevent="saveTeam">
				<div class="row" style="padding-top: 5px;">
					<div class="form-group clearfix">
						<label class="control-label col-md-4">Material Name:</label>
						<div class="col-md-8">
							<input type="text" class="form-control" v-model="material.name" required>
						</div>
					</div>
					<div class="form-group clearfix">
						<label class="control-label col-md-4"></label>
						<div class="col-md-4">
							<input type="submit" name="submit" class="btn btn-primary btn-block pull-right col-md-4" value="Save">
						</div>
					</div>
				</div>
			</form>
			<!-- Client entry -->
		</div><!-- /.col -->
	</div><!-- /.row -->
	<hr style="margin-top: 10px;margin-bottom: 10px;">
	<div class="row">
		<div class="col-md-6 col-md-offset-3">		
			<div class="col-md-12 form-inline">
				<div class="form-group">
					<label for="filter" class="sr-only">Filter</label>
					<input type="text" class="form-control" v-model="filter" placeholder="Filter">
				</div>
			</div>
			<div class="col-md-12">
				<div class="table-responsive">
					<datatable :columns="columns" :data="materials" :filter-by="filter" style="margin-bottom: 5px;">
						<template scope="{ row }">
							<tr>
								<td>{{ row.sl }}</td>
								<td>{{ row.name }}</td>
								<td>
									<button type="button" class="button edit" @click="editMaterial(row)">
									<i class="fa fa-pencil"></i>
									</button>
									<button type="button" class="button" @click="deleteMaterial(row.id)">
										<i class="fa fa-trash"></i>
									</button>
								</td>
							</tr>
						</template>
					</datatable>
					<datatable-pager v-model="page" type="abbreviated" :per-page="per_page" style="margin-bottom: 50px;"></datatable-pager>
				</div>			
				
			</div>
		</div>
	</div>
</div><!-- /.main-content -->

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>

<script>
	new Vue({
		el: '#materials',
		data(){
			return {
				material: {
					id: 0,
					name: '',
				},
				materials: [],
				columns: [
                    { label: 'Serial', field: 'sl', align: 'center', filterable: false },
                    { label: 'Material Name', field: 'name', align: 'center' },
                    { label: 'Action', align: 'center', filterable: false }
                ],
                page: 1,
                per_page: 10,
                filter: ''
			}
		},

		created(){
			this.getMaterials();
		},

		methods: {
			getMaterials(){
				axios.get("/get-materials").then(res => {
					this.materials = res.data.map((item, sl)=>{
						item.sl =  sl + 1;
						return item;
					});
				})
			},
			async saveTeam(){

				let url = "";
				if(this.material.id != 0){
					url = "/update-material";
				} else {
					url = "/add-material"
					// delete this.material.id
				}

				await axios.post(url, this.material)
				.then(res=>{
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.resetForm();
						this.getMaterials();
					}
				})
			},
			editMaterial(material)
			{
				let keys = Object.keys(this.material).forEach(key => {
					this.material[key] = material[key];
				})
			},
			deleteMaterial(id) {
				let deleteConfirm = confirm('Are you sure?');
				if(deleteConfirm == false){
					return;
				}
				axios.post("/delete-material", {id: id})
				.then(res => {
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.getMaterials();
					}
				})
			},
			resetForm(){
				let keys = Object.keys(this.material);
				keys.forEach(key => {
					if(typeof(this.material[key]) == 'string'){
						this.material[key] = '';
					} else if(typeof(this.material[key]) == 'number'){
						this.material[key] = 0;
					}
				})
			}
		}
	})
</script>
