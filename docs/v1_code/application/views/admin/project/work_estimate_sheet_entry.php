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

            <div class="form-group clearfix">
              <label class="control-label col-md-3">Select Work Item:</label>
                <div class="col-md-8">
                    <select class="form-control" v-if="work_items.length == 0"></select>
                    <v-select v-bind:options="work_items" v-model="selectedWorkItem" label="name"></v-select>
                </div>
                <div class="col-md-1" style="padding:0;margin-left: -15px;"><a href="/workItem" target="_blank"
                        class="add-button"><i class="fa fa-plus"></i></a></div>
            </div>

        </div>

    </div>

    <div class="row">
        <form v-on:submit.prevent="addToMaterial">
            <div class="col-md-3">
                <label for="">Description of Work</label>
                <div>
                    <input type="text" class="form-control" v-model="workEstimateCart.work_description">
                </div>
            </div>

            <div class="col-md-1">
                <label for="">Level</label>
                <div>
                    <input type="text" class="form-control" v-model="workEstimateCart.level">
                </div>
            </div>

            <div class="col-md-1">
                <label for="">Location</label>
                <div>
                    <input type="text" class="form-control" v-model="workEstimateCart.location">
                </div>
            </div>

            <div class="col-md-4">
                <div class="row text-center">
                    <div class="col-md-12">
                        <label style="font-wight:bold;"> Measurment </label>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Length" v-model="workEstimateCart.length"
                            @input="totalQuantity">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Width" v-model="workEstimateCart.width"
                            @input="totalQuantity">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Height" v-model="workEstimateCart.height"
                            @input="totalQuantity">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Nose" v-model="workEstimateCart.nose"
                            @input="totalQuantity">
                    </div>
                </div>
            </div>

            <div class="col-md-1">
                <label for="">Unit</label>
                <div>
                    <input type="text" class="form-control" v-model="workEstimateCart.unit">
                </div>
            </div>


            <div class="col-md-1">
                <label for="">Qty</label>
                <div>
                    <input type="text" class="form-control" v-model="workEstimateCart.quantity">
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

            <div class="col-md-3">
                <label for="">Name of Materials</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.work_description">
                </div>
            </div>

            <div class="col-md-1">
                <label for="">Level</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.level">
                </div>
            </div>

            <div class="col-md-1">
                <label for="">Location</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.location">
                </div>
            </div>

            <div class="col-md-4">
                <div class="row text-center">
                    <div class="col-md-12">
                        <label style="font-wight:bold;"> Measurment </label>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Length" v-model="visit.length"
                            @input="totalQuantity">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Width" v-model="visit.width"
                            @input="totalQuantity">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Height" v-model="visit.height"
                            @input="totalQuantity">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Nose" v-model="visit.nose"
                            @input="totalQuantity">
                    </div>
                </div>
            </div>

            <div class="col-md-1">
                <label for="">Unit</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.unit">
                </div>
            </div>

            <div class="col-md-1">
                <label for="">Quantity</label>
                <div>
                    <input type="text" class="form-control" v-model="visit.quantity">
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
                    <input type="submit" :class="project.Work_Estimate_SlNo == 0 ? 'addButton' : 'updateButton'"
                        :value="project.Work_Estimate_SlNo != 0 ? 'Update' : 'Save' " v-on:click="saveProjectVisit">
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
                <datatable :columns="columns" :data="all_materials" :filter-by="filter">
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
                                <button type="button" class="button" @click="deleteProduct(row.Work_Estimate_SlNo)">
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
                Work_Estimate_SlNo: '',
                address: '',
                date: '',
                work_name: '',
                project_id: '',
                work_item_id: '',
				length : 0,
				width: 0,
				height: 0,
				nose: 0
            },
            projects: [],
            selectedWorkItem : null,
            work_items: [],
            workEstimateCart: {
                work_description: '',
                level: '',
                location: '',
                measurment: '',
                unit: '',
                quantity: 0,
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
        this.getWorkItems();
    },
    methods: {
        changeIsService() {
            if (this.product.is_service) {
                this.product.Product_Purchase_Rate = 0;
            }
        },
        getProjects() {
            axios.get('/get-projects').then(res => {
                this.projects = res.data;
            })
        },

        getProjectVisit() {
            axios.get('/get_work_estimate').then(res => {
                this.all_materials = res.data;
            })
        },
        getWorkItems ()
        {
            axios.get('/get-workItems').then(res => {
                this.work_items = res.data;
            })
        },

        totalQuantity() {
            const length = parseFloat(this.workEstimateCart.length) || 0;
            const width = parseFloat(this.workEstimateCart.width) || 0;
            const height = parseFloat(this.workEstimateCart.height) || 0;
            const nose = parseFloat(this.workEstimateCart.nose) || 0;

            this.workEstimateCart.quantity = length * width * height * nose;
        },


        addToMaterial() {
            let material = {
                work_description: this.workEstimateCart.work_description,
                level: this.workEstimateCart.level,
                location: this.workEstimateCart.location,
                length: this.workEstimateCart.length,
				width: this.workEstimateCart.width,
				height: this.workEstimateCart.height,
				nose: this.workEstimateCart.nose,
                unit: this.workEstimateCart.unit,
                quantity: this.workEstimateCart.quantity,
            }

			console.log(material);

            this.materialCart.unshift(material);
            this.clearmaterialCart();
        },

        removeItem(ind) {
            this.materialCart.splice(ind, 1);
        },
        async saveProjectVisit() {


            if (this.selectedProject == null) {
                alert("Please Select Project");
                return;
            }

            if (this.selectedWorkItem == null) {
                alert("Please Select Work Item");
                return;
            }

            if (this.materialCart.length == 0) {
                alert("Your Visiting cart Is Empty");
                return;
            }

            this.project.project_id = this.selectedProject.id;
            this.project.work_item_id = this.selectedWorkItem.id;
            // this.product.Unit_ID = this.selectedUnit.Unit_SlNo;

            let url = '/add_work_estimate';
            if (this.project.Work_Estimate_SlNo != 0) {
                url = '/update_work_estimate';
            }

            let data = {
                project_visit: this.project,
                cart: this.materialCart
            }

            await axios.post(url, data)
                .then(res => {
                    let r = res.data;
                    alert(r.message);
                    if (r.success) {
						window.location.reload();
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

            this.selectedWorkItem = {
                id: product.work_item_id,
                name: product.work_item_name
            }

            console.log(product.details);

            product.details.forEach((item) => {
                let material = {
                    work_description: item.work_description,
                    level: item.level,
                    location: item.location,
                    length: item.length,
					height: item.height,
					width: item.width,
					nose: item.nose,
                    unit: item.unit,
                    quantity: item.quantity,
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
            axios.post('/delete_work_estimate', {
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
            this.workEstimateCart = {
                work_description: '',
                level: '',
                location: '',
                measurment: '',
                unit: '',
                quantity: 0,
            }
        },
    }
})
</script>