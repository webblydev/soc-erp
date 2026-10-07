
<div id="team-member">
	<div class="row">
		<div class="col-sm-12 form-inline">
			<div class="form-group">
				<label for="filter" class="sr-only">Filter</label>
				<input type="text" class="form-control" v-model="filter" placeholder="Filter">
			</div>
		</div>
		<div class="col-md-12">
			<div class="table-responsive">
				<datatable :columns="columns" :data="users" :filter-by="filter" style="margin-bottom: 5px;">
					<template scope="{ row }">
						<tr>
							<td>{{ row.sl }}</td>
							<td>{{ row.name }}</td>
							<td>{{ row.phone }}</td>
							<td>{{ row.email }}</td>
							<td style="text-transform: capitalize;">Team {{ row.team_name }}</td>
						</tr>
					</template>
				</datatable>
				<datatable-pager v-model="page" type="abbreviated" :per-page="per_page" style="margin-bottom: 50px;"></datatable-pager>
			</div>
		</div>
	</div>
</div><!-- /.main-content -->



<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>

<script>
	new Vue({
		el: '#team-member',
		data(){
			return {
				users: [],
				columns: [
                    { label: 'Sl', field: 'sl', align: 'center' },
                    { label: 'Name', field: 'name', align: 'center' },
                    { label: 'Phone', field: 'phone', align: 'center' },
                    { label: 'E-mail', field: 'email', align: 'center' },
                    { label: 'Team', field: 'team_name', align: 'center' },
                ],
                page: 1,
                per_page: 10,
                filter: ''
			}
		},

		created(){
			this.getUsers();
		},

		methods: {
			getUsers(){
				axios.get('<?php echo base_url('get-team-member') ?>').then(res => {
					this.users = res.data.map((ele,ind)=>{
							ele.sl = ind+parseFloat(1)
							return ele;
					});
				})
			}
		}
	})
</script>

