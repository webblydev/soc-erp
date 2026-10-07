<?php
$company = $this->db->query('select * from tbl_company')->row();
?>
<!DOCTYPE html>
<html>
<head>
  <title>login page</title>
  <link rel="stylesheet" type="text/css" href="<?php echo base_url('assets/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" type="text/css" href="<?php echo base_url('assets/css/login.css') ?>">
</head>
<body style="background: url(assets/3409297.jpg);background-repeat: no-repeat;background-position: center center;">
<div class="container">
  <div class="contant">
    <h2 class="headding"> Admin Login</h2>
    <div class="login">
      <div class="left-cont">
        <!-- <div class="overlay-div"> -->
          <div class="company-feature">
            <div class="com-image">
              <img src="<?php echo base_url('assets/images/').$company->image; ?>" style="width: 100%;height: 125px; border-radius:10px;padding-top: 5px;">
            </div>
            
          </div>
        <!-- </div> -->
        <div class="company-info">
          <h4><?php echo $company->name; ?></h4>
          <div class="com-add">
            <div class="com-profile">
              <strong>Address</strong> : <?php echo $company->address; ?> <br>
              <strong>Phone</strong> : <?php echo $company->phone; ?> <br>
              <strong>E-mail</strong> : <?php echo $company->email; ?>
            </div>
          </div>
        </div>
        <div class="develop_by"><strong style="font-size: 10px">Develop By </strong> <a href="http://linktechbd.com/">Link Up Technology Ltd.</a></div>
        <div class="corcel">
          <div class="round">
            <div class="inner-round">
              <div class="inner-logo"><!-- ERP --></div>
            </div>
          </div>
        </div>
        
      </div>
      <div class="right-cont">
        <div class="login-form">
          <div class="form">
            <h4>Sign In Form</h4>
            <div id="output" class="text-success"></div>
           <div style="padding-bottom: 15px;" id="error" class="text-danger"></div>
            <form id="loginForm">
              <div class="form-group">
                <input type="text" name="user_name" id="user_name" class="form-control" placeholder="User Name">
              </div>
              <div class="form-group">
                <input type="password" name="password" id="password" class="form-control" placeholder="Password">
              </div>
              <div class="form-group">
                <input type="hidden" name="action" id="action" value="login">
                <input type="submit" name="submit" class="btn btn-info btn-block" value="Login">
              </div>
            </form>
          </div>
        </div>
        
      </div>
      <div class="clr"></div>
    </div>
  </div>

</div>
<script src="<?php echo base_url('assets/js/jquery-2.1.4.min.js') ?>"></script>
<script src="<?php echo base_url('assets/js/bootstrap.min.js') ?>"></script>
<script>
  $(document).ready(function(){
    $(document).on('submit','#loginForm',function(e){
 
        e.preventDefault();
        var action=$('#action','#loginForm').val();
        var base_url="<?php echo base_url('login-form') ?>";

          $.ajax({
              url:base_url,
              method:'post',
              data:new FormData(this),
              contentType:false,
              processData:false,
              success: function(data){
                if (data.trim()=='success') {
                  window.location.href='<?php echo base_url('dashboard') ?>';
                }else{
                 $('#error').html(data);
                }
              }
          });
        })
      })
</script>
</body>
</html>