<style>
.v-select {
    margin-bottom: 5px;
}

.v-select.open .dropdown-toggle {
    border-bottom: 1px solid #ccc;
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

#project label {
    font-size: 13px;
}

#project select {
    border-radius: 3px;
}

#project .add-button {
    padding: 2.5px;
    width: 28px;
    background-color: #298db4;
    display: block;
    text-align: center;
    color: white;
}

#project .add-button:hover {
    background-color: #41add6;
    color: white;
}

.addButton {
    background-color: green;
    border: 1px solid #ccc;
    border-radius: 7px;
    color: #fff;
    padding: 5px 10px;
    margin-top: 20px;
}

.updateButton {
    background-color: #d35400;
    border: 1px solid #ccc;
    border-radius: 7px;
    color: #fff;
    padding: 5px 10px;
    margin-top: 20px;
}

.DeleteButton {
    background-color: red;
    border: 1px solid #ccc;
    border-radius: 7px;
    color: #fff;
    padding: 5px 10px;
    margin-top: 20px;
}
</style>
<div id="project">
    <div class="row" style="margin-top: 10px;margin-bottom:15px;border-bottom: 1px solid #ccc;padding-bottom: 15px;">
        <div class="col-md-8 col-md-offset-2">

            <div class="form-group clearfix">
                <label class="control-label col-md-3">Select Project:</label>
                <div class="col-md-8">
                    <select class="form-control" v-if="projects.length == 0"></select>
                    <v-select v-bind:options="projects" v-model="selectedProject" label="name"></v-select>
                </div>
                <div class="col-md-1" style="padding:0;margin-left: -15px;"><a href="/project" target="_blank"
                        class="add-button"><i class="fa fa-plus"></i></a></div>
            </div>

            <div class="form-group clearfix">
                <label class="control-label col-md-3">Address:</label>
                <div class="col-md-8">
                    <input type="text" class="form-control" v-model="project.address" required>
                </div>
            </div>

            <div class="form-group clearfix">
                <label class="control-label col-md-3">date:</label>
                <div class="col-md-8">
                    <input type="date" class="form-control" v-model="project.date" required>
                </div>
            </div>

            <div class="form-group clearfix">
                <label class="control-label col-md-3"> Name of Work:</label>
                <div class="col-md-8">
                    <input type="text" class="form-control" v-model="project.work_name">
                </div>
            </div>

        </div>

    </div>

    <div class="row">
        <form v-on:submit.prevent="addToMaterial">
            <!-- <div class="col-md-4"> 
						<label for="">Name of Materials</label>
						<div>
							<input type="text" class="form-control" v-model="materialDetailsCart.material_name">
						</div>
					</div> -->

            <div class="col-md-4">
                <div class="form-group clearfix">
                    <label class="control-label">Select Materials:</label>
                        <select class="form-control" v-if="materials.length == 0"></select>
                        <v-select v-bind:options="materials" v-model="selectedMaterial" label="name"></v-select>
                   </div>
            </div>

            <div class="col-md-2">
                <label for="">unit</label>
                <div>
                    <input type="text" class="form-control" v-model="materialDetailsCart.unit">
                </div>
            </div>

            <div class="col-md-2">
                <label for="">Total Estimated Qty</label>
                <div>
                    <input type="text" class="form-control" v-model="materialDetailsCart.total_estimated_qty">
                </div>
            </div>

            <div class="col-md-3">
                <label for="">Purpose of Revised Estimate</label>
                <div>
                    <input type="text" class="form-control" v-model="materialDetailsCart.purpose_estimate">
                </div>
            </div>

            <div class="col-md-1">
                <div>
                    <button type="submit" class="addButton"> Add + </button>
                </div>
            </div>

        </form>
    </div>

    <div class="vist-details" style="margin-top:25px;">
        <div class="row" v-for="(visit, sl) in materialCart" style="margin-bottom: 15px;">

            <div class="col-md-4">
                <label for="">Name of Materials</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.material_name" readonly>
                </div>
            </div>

            <div class="col-md-2">
                <label for="">unit</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.unit">
                </div>
            </div>

            <div class="col-md-2">
                <label for="">Total Estimated Qty</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.total_estimated_qty">
                </div>
            </div>

            <div class="col-md-3">
                <label for="">Purpose of Revised Estimate</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.purpose_estimate">
                </div>
            </div>

            <div class="col-md-1">
                <div>
                    <button type="submit" class="DeleteButton" v-on:click="removeItem(sl)"> Delete </button>
                </div>
            </div>

        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="form-group clearfix">
                <div class="col-md-7 col-md-offset-5">
                    <input type="submit" :class="project.Material_Estimate_SlNo == 0 ? 'addButton' : 'updateButton'"
                        :value="project.Material_Estimate_SlNo != 0 ? 'Update' : 'Save' " v-on:click="saveProjectVisit">
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 form-inline">
            <div class="form-group">
                <label for="filter" class="sr-only">Filter</label>
                <input type="text" class="form-control" v-model="filter" placeholder="Filter">
            </div>
        </div>
        <div class="col-md-12">
            <div class="table-responsive">
                <datatable :columns="columns" :data="all_materials.material_estimate" :filter-by="filter">
                    <template scope="{ row }">
                        <tr>
                            <td>{{ row.name }}</td>
                            <td>{{ row.work_name }} </td>
                            <td>{{ row.address }}</td>
                            <td>{{ row.date }}</td>
                            <td>
                                <?php if ($this->session->userdata('accountType') != 'u') {?>
                                <button type="button" class="button edit" @click="editProject(row)">
                                    <i class="fa fa-pencil"></i>
                                </button>
                                <button type="button" class="button" @click="deleteProduct(row.Material_Estimate_SlNo)">
                                    <i class="fa fa-trash"></i>
                                </button>
                                <?php }?>
                            </td>
                        </tr>
                    </template>
                </datatable>
                <datatable-pager v-model="page" type="abbreviated" :per-page="per_page"></datatable-pager>
            </div>
        </div>
    </div>

</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
Vue.component('v-select', VueSelect.VueSelect);
new Vue({
    el: '#project',
    data() {
        return {
            project: {
                Material_Estimate_SlNo: '',
                address: '',
                date: '',
                work_name: '',
                project_id: '',
            },
            projects: [],
			selectedMaterial : null,
			materials: [],
            materialDetailsCart: {
                material_name: '',
				material_id: null,
                unit: '',
                total_estimated_qty: 0,
                purpose_estimate: ''
            },
            materialCart: [],
            selectedProject: null,
            all_materials: [],

            columns: [{
                    label: 'Project Name',
                    field: 'name',
                    align: 'center'
                },
                {
                    label: 'Work Name',
                    field: 'work_name',
                    align: 'center'
                },
                {
                    label: 'Address',
                    field: 'address',
                    align: 'center'
                },
                {
                    label: 'Date',
                    field: 'date',
                    align: 'center'
                },
                {
                    label: 'Action',
                    align: 'center',
                    filterable: false
                }
            ],
            page: 1,
            per_page: 10,
            filter: ''
        }
    },
    created() {
        this.getProjects();
        this.getProjectVisit();
		this.getMaterials();
    },
    methods: {
        changeIsService() {
            if (this.product.is_service) {
                this.product.Product_Purchase_Rate = 0;
            }
        },
		getMaterials(){
				axios.get("/get-materials").then(res => {
					this.materials = res.data.map((item, sl)=>{
						item.sl =  sl + 1;
						return item;
					});
				})
			},
        getProjects() {
            axios.get('/get-projects').then(res => {
                this.projects = res.data;
            })
        },

        getProjectVisit() {
            axios.get('/get_project_material_estimate').then(res => {
                this.all_materials = res.data;
            })
        },

        addToMaterial() {
            let material = {
                material_name: this.selectedMaterial.name,
				material_id: this.selectedMaterial.id,
                unit: this.materialDetailsCart.unit,
                total_estimated_qty: this.materialDetailsCart.total_estimated_qty,
                purpose_estimate: this.materialDetailsCart.purpose_estimate,
            }

            this.materialCart.unshift(material);
            this.clearmaterialCart();
        },

        removeItem(ind) {
            this.materialCart.splice(ind, 1);
        },
        saveProjectVisit() {
            if (this.selectedProject == null) {
                alert("Please Select Project");
                return;
            }

            if (this.materialCart.length == 0) {
                alert("Your Visiting cart Is Empty");
                return;
            }

            this.project.project_id = this.selectedProject.id;
            // this.product.Unit_ID = this.selectedUnit.Unit_SlNo;

            let url = '/add_project_material_estimate';
            if (this.project.Material_Estimate_SlNo != 0) {
                url = '/update_project_material_estimate';
            }

            let data = {
                project_visit: this.project,
                cart: this.materialCart
            }

            axios.post(url, data)
                .then(res => {
                    let r = res.data;
                    alert(r.message);
                    if (r.success) {
                        this.clearForm();
                        // this.product.Product_Code = r.productId;
                        this.getProjectVisit();
                    }
                })

        },
        editProject(product) {
            let keys = Object.keys(this.project);
            keys.forEach((key) => {
                this.project[key] = product[key];
            })
            console.log(keys);
            this.selectedProject = {
                id: product.project_id,
                name: product.name
            }

            console.log(product.details);

            product.details.forEach((item) => {
                let material = {
                    material_name: item.material_name,
                    unit: item.unit,
                    total_estimated_qty: item.total_estimated_qty,
                    purpose_estimate: item.purpose_estimate,
                }

                this.materialCart.unshift(material);

            })
        },
        deleteProduct(productId) {
            let deleteConfirm = confirm('Are you sure?');
            if (deleteConfirm == false) {
                return;
            }
            console.log(productId);
            axios.post('/delete_material_estimate', {
                estimate_id: productId
            }).then(res => {
                let r = res.data;
                alert(r.message);
                if (r.success) {
                    this.getProjectVisit();
                }
            })
        },
        clearForm() {
            let keys = Object.keys(this.project);
            keys.forEach(key => {
                if (typeof(this.project[key]) == "string") {
                    this.project[key] = '';
                } else if (typeof(this.project[key]) == "number") {
                    this.product[key] = 0;
                }
            })
            this.selectedProject = null;
            this.materialCart = [];

        },
        clearmaterialCart() {
            this.materialDetailsCart = {
                material_name: '',
                unit: '',
                total_estimated_qty: 0,
                purpose_estimate: ''
            }
        },
    }
})
</script>