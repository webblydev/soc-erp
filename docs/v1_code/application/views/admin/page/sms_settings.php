<style>
    #smsSettings label {
        font-size: 13px;
    }
</style>
<div id="smsSettings">
    <div class="row">
        <div class="col-md-6">
            <form @submit.prevent="saveSettings">
                <div class="form-group clearfix">
                    <label class="control-label col-md-4">Enable SMS</label>
                    <div class="col-md-8">
                        <input type="checkbox" v-model="settings.sms_enabled" true-value="true" false-value="false">
                    </div>
                </div>
                <div class="form-group clearfix">
                    <label class="control-label col-md-4">API Key</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" v-model="settings.api_key" required>
                    </div>
                </div>
                <div class="form-group clearfix">
                    <label class="control-label col-md-4">URL</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" v-model="settings.url" required>
                    </div>
                </div>
                <div class="form-group clearfix">
                    <label class="control-label col-md-4">Bulk URL</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" v-model="settings.bulk_url" required>
                    </div>
                </div>
                <div class="form-group clearfix">
                    <label class="control-label col-md-4">Sender Id</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" v-model="settings.sender_id" required>
                    </div>
                </div>
                <div class="form-group clearfix">
                    <label class="control-label col-md-4">SMS Type</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" v-model="settings.sms_type" required>
                    </div>
                </div>
                <div class="form-group clearfix">
                    <label class="control-label col-md-4">Sender Name</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" v-model="settings.sender_name" required>
                    </div>
                </div>
                <div class="form-group clearfix">
                    <label class="control-label col-md-4">Sender Phone</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" v-model="settings.sender_phone" required>
                    </div>
                </div>
                <div class="form-group clearfix">
                    <div class="col-md-8 col-md-offset-4">
                        <input type="submit" value="Save" class="btn btn-success btn-sm">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>

<script>
    new Vue({
        el: '#smsSettings',
        data() {
            return {
                settings: {
                    sms_enabled: 'false',
                    api_key: '',
                    url: '',
                    bulk_url: '',
                    sms_type: '',
                    sender_id: '',
                    sender_name: '',
                    sender_phone: ''
                }
            }
        },
        created() {
            this.getSettings();
        },
        methods: {
            getSettings() {
                axios.get('/get_sms_settings').then(res => {
                    if(res.data != null){
                        this.settings = res.data;
                    }
                })
            },
            saveSettings() {
                axios.post('/save_sms_settings', this.settings)
                .then(res => {
                    let r = res.data;
                    alert(r.message);
                })
                .catch(error => alert(error.response.statusText))
            }
        }
    })
</script>
