<?php
// Start Session
session_start();

// Import Configurations
$settings=json_decode(file_get_contents(dirname(__FILE__) . '/settings.json'),true);

// Disable Error Reporting
// error_reporting(0);

if(isset($_SESSION['LDAPUSER'])){
  if(isset($_POST['ACTION']) and ($_POST['ACTION'] == "logout")){
    session_destroy();
    header('Refresh: ' . 0);
  }
  if(isset($_GET['ACTION']) and ($_GET['ACTION'] == "logout")){
    session_destroy();
    header('Refresh: ' . 0);
  }
} else {
  if(isset($_POST['LDAPUSER']) and isset($_POST['LDAPPASS']) and ($_POST['LDAPUSER'] != "") and ($_POST['LDAPPASS'] != "")){
    $LDAPUSER=$_POST['LDAPUSER'];
    $LDAPPASS=$_POST['LDAPPASS'];
    $LDAP = ldap_connect("ldap://".$settings['ldap']['host']);
    ldap_set_option($LDAP, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($LDAP, LDAP_OPT_REFERRALS, 0);
    $bind = @ldap_bind($LDAP, $settings['ldap']['domain']."\\".$LDAPUSER, $LDAPPASS);
    if($bind){
      $_SESSION['LDAPUSER']=$LDAPUSER;
    } else {
      $ERROR="Wrong username and/or password";
    }
  }
}
if(isset($_SESSION['LDAPUSER'])){
    include('Net/SSH2.php');
    include('Crypt/RSA.php');
    $ssh = new Net_SSH2($settings['vdi']['host']);
    $key = new Crypt_RSA();
    if(isset($settings['vdi']['password']) and ($settings['vdi']['password'] != "")){
        if(isset($settings['vdi']['key']) and ($settings['vdi']['key'] != "")){
            $key->setPassword($settings['vdi']['password']);
            $key->loadKey(file_get_contents($settings['vdi']['key']));
            if (!$ssh->login('username', $key)) {
                $ERROR="Wrong username and/or password and/or key";
            }
        } else {
            if (!$ssh->login($settings['vdi']['username'], $settings['vdi']['password'])) {
                $ERROR="Wrong username and/or password";
            }
        }
    } else {
        if(isset($settings['vdi']['key']) and ($settings['vdi']['key'] != "")){
            $key->loadKey(file_get_contents($settings['vdi']['key']));
            if (!$ssh->login('username', $key)) {
                $ERROR="Wrong username and/or key";
            }
        }
    }
    if (!isset($ERROR)){
        $VMName=$_SESSION['LDAPUSER'];
        $VMID=$ssh->exec("qm list | egrep -i $VMName | awk {'print $1'}");
        if(isset($_POST['ACTION'])){
            switch ($_POST['ACTION']) {
                case "stop":
                    $LOGGER=$ssh->exec("qm stop $VMID");
                    $ALERT="Session Stopped";
                    break;
                case "start":
                    $LOGGER=$ssh->exec("qm start $VMID");
                    $ALERT="Session Started";
                    break;
                case "restart":
                    $LOGGER=$ssh->exec("qm stop $VMID");
                    sleep(5);
                    $LOGGER.=$ssh->exec("qm start $VMID");
                    $ALERT="Session Restarted";
                    break;
                default:
                    $LOGGER="The requested action cannot be completed";
            }
        }
        $VMStatus=$ssh->exec("qm status $VMID");
        $VMStatus=str_replace("status: ","",$VMStatus);
        $VMDNS=$VMName.$settings['vdi']['domain'];
        $VMService=$ssh->exec("nmap -Pn -p ".$settings['vdi']['port']." $VMDNS | grep ".$settings['vdi']['port']." | awk {'print $2'}");
        $VDILatency=$ssh->exec("fping -c 1 $VMDNS 2>/dev/null | awk {'print $6'}");
        $VMLatency=exec("fping -c 1 $VMDNS 2>/dev/null | awk {'print $6'}");
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Tiny web interface to manage VDI based on Proxmox">
    <meta name="author" content="Louis Ouellet">
    <link rel="icon" href="favicon.ico">
    <title>VDI Session Manager</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <style>
        body {
            padding-top: 5rem;
        }
        .starter-template {
            padding: 3rem 1.5rem;
            text-align: center;
        }
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
                font-size: 3.5rem;
            }
        }
    </style>
  </head>
  <body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
      <span class="navbar-brand">VDI Session Manager</span>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
      <div class="collapse navbar-collapse" id="navbarCollapse">
        <ul class="navbar-nav mr-auto">
          <form class="form-inline my-2 my-lg-0" method="post">
            <input class="btn btn-outline-primary my-2 my-sm-0 tooltip-test" data-toggle="modal" data-target="#Loading" type="submit" title="Refresh" value="Refresh">
          </form>
        </ul>
        <?php if(isset($_SESSION['LDAPUSER'])){ ?>
          <form class="form-inline my-2 my-lg-0" method="post">
            <input name="ACTION" type="text" style="display:none" value="logout">
            <input class="btn btn-primary my-2 my-sm-0 tooltip-test" title="Logout" data-toggle="modal" data-target="#Loading" type="submit" value="<?php echo $_SESSION['LDAPUSER'] ?>">
          </form>
        <?php } else { ?>
          <form class="form-inline my-2 my-lg-0" method="post">
            <input class="form-control mr-sm-2" name="LDAPUSER" type="text" placeholder="Username" aria-label="Username">
            <input class="form-control mr-sm-2" name="LDAPPASS" type="password" placeholder="Password" aria-label="Password">
            <input class="btn btn-primary my-2 my-sm-0 tooltip-test" data-toggle="modal" data-target="#Loading" type="submit" title="Login" value="Login">
          </form>
        <?php } ?>
      </div>
    </nav>
    <main role="main" class="container">
    <div class="modal fade" id="Loading" aria-hidden="true" style="z-index:999999999">
      <div class="modal-dialog modal-dialog-centered">
        <div class="spinner-border text-primary" style="width: 100px; height: 100px; margin-left:175px;" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>
    </div>
    <?php if(isset($_SESSION['LDAPUSER'])){ ?>
        <?php if(!isset($ERROR)){ ?>
            <?php if(isset($ALERT) and ($ALERT != "")){ ?>
                <div class="alert alert-success" role="alert">
                  <?php echo $ALERT ?>
                </div>
            <?php } ?>
            <?php if(isset($LOGGER) and ($LOGGER != "")){ ?>
                <div class="alert alert-danger" role="alert">
                  <?php echo $LOGGER ?>
                </div>
            <?php } ?>
            <div class="jumbotron">
                <h1 class="display-4">Current Status : <?php echo $VMStatus ?></h1>
                <p class="lead">You can control your session using the controls below. <span class="badge badge-warning">It may take up to 15 min for an action to complete</span></p>
                <hr class="my-4">
                <p>If your requested action takes more then 15min to complete, contact your administrator.</p>
                <a class="btn btn-success btn-lg tooltip-test" title="Start your Session" style="color:#FFF;cursor:pointer;" data-toggle="modal" data-target="#ActionStart" role="button">Start</a>
                <a class="btn btn-danger btn-lg tooltip-test" title="Stop your Session" style="color:#FFF;cursor:pointer;" data-toggle="modal" data-target="#ActionStop" role="button">Stop</a>
                <a class="btn btn-warning btn-lg tooltip-test" title="Restart your Session" style="color:#FFF;cursor:pointer;" data-toggle="modal" data-target="#ActionRestart" role="button">Restart</a>
            </div>
            <div class="card">
              <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><h5>Service : <span class="badge badge-<?php if($VMService != ""){ echo "success"; } else { echo "danger"; } ?> tooltip-test" title="Service status"><?php if($VMService != ""){ echo "Open"; } else { echo "Closed"; } ?></span></h5></div>
                    <div class="col-md-4"><h5>VM Latency : <span class="badge badge-<?php if(($VMLatency >= 450) or ($VMLatency == "")){ echo "danger"; } elseif($VMLatency >= 150){ echo "warning"; } else { echo "success"; } ?> tooltip-test" title="Ping from VDI Session Manager"><?php echo $VMLatency ?> ms</span></h5></div>
                    <div class="col-md-4"><h5>VDI Latency : <span class="badge badge-<?php if(($VDILatency >= 450) or ($VDILatency == "")){ echo "danger"; } elseif($VDILatency >= 150){ echo "warning"; } else { echo "success"; } ?> tooltip-test" title="Ping from VDI Server"><?php echo $VDILatency ?>ms</span></h5></div>
                </div>
              </div>
            </div>
            <div class="modal fade" id="ActionStop" tabindex="-1" role="dialog" aria-labelledby="ActionStopLabel" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header bg-danger">
                    <h5 class="modal-title" id="ActionStopLabel" style="color:#FFF">Are you sure?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    You are about to stop your session. You will no longer be able to access it.
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <form method="post">
                        <input name="ACTION" type="text" style="display:none" value="stop">
                        <input class="btn btn-danger" style="color:#FFF;cursor:pointer;" data-toggle="modal" data-target="#Loading" type="submit" value="Stop">
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal fade" id="ActionRestart" tabindex="-1" role="dialog" aria-labelledby="ActionRestartLabel" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="ActionRestartLabel" style="color:#FFF">Are you sure?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    You are about to restart your session. It may take up to 15 min for your session to be available.
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <form method="post">
                        <input name="ACTION" type="text" style="display:none" value="restart">
                        <input class="btn btn-warning" style="color:#FFF;cursor:pointer;" data-toggle="modal" data-target="#Loading" type="submit" value="Restart">
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal fade" id="ActionStart" tabindex="-1" role="dialog" aria-labelledby="ActionStartLabel" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header bg-success">
                    <h5 class="modal-title" id="ActionRestartLabel" style="color:#FFF">Are you sure?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    You are about to start your session.
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <form method="post">
                        <input name="ACTION" type="text" style="display:none" value="start">
                        <input class="btn btn-success" style="color:#FFF;cursor:pointer;" data-toggle="modal" data-target="#Loading" type="submit" value="Start">
                    </form>
                  </div>
                </div>
              </div>
            </div>
        <?php } else { ?>
            <div class="starter-template">
                <h1>503 - Service Not Available</h1>
                <p>Please contact your administrator</p>
            </div>
        <?php } ?>
    <?php } else { ?>
        <?php if(isset($ERROR)){ ?>
            <div class="starter-template">
                <h1><?php echo $ERROR ?></h1>
            </div>
        <?php } else { ?>
            <div class="starter-template">
                <h1>403 - Permission Denied</h1>
                <p>Have you logged in?</p>
            </div>
        <?php } ?>
    <?php } ?>

    </main>
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
  </body>
</html>
