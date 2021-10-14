# VDI Session Manager

This repository contains a small software to allow users from a LDAP/ADDC Server to manage the power options of their own sessions.

## Little Bit of Background

I built a system running Proxmox Hypervisor to run multiple Virtual Desktops running Windows. And I needed to provide a secure way for the users to control the power options of their Virtual Environment. The issue came when users accidentally shutdown their VM to try to resolve an issue. But were ultimately unable to start their VM due to access resctrictions. Thus managers would have the task of taking care of those actions. Then this thought came to me.

## Requirements

This software requires access to these commands on the VDI server:
- qm list
- qm start
- qm stop
- fping -> May required to be installed
- nmap -> May required to be installed

## How it Works

Basically when the login is submitted, the software tries the set of credentials against the LDAP Host provided in the `config.php` file. Then when a user initiates one of the 3 actions (Start, Stop, Restart) the software connects to the VDI server using SSH to perform the requested task.

## How to Configure

Before you can start using this software we need to perform some task on the VDI Server. You will need to install the following 2 packages :
- fping
- nmap

You can do so by running :
```bash
sudo apt-get install fping nmap -y
```

We will also need some modifications on your DNS server. You will need to add an entry like this username.domain to point to each Virtual Desktop. This will allow this software to provide information on the status of the Virtual Desktop. Mainly is the watch port open and can it receive ping requests.

Now that our VDI Server has the needed packages, we can proceed to install the VDI Session Manager. This software only requires the following packages :
- fping
- nmap
- apache2
- php
- php-ldap

You can do so by running :
```bash
sudo apt-get install fping nmap apache2 php php-ldap -y
```

Now we can install the software. Move to the public directory and clone this repository.

You can do so by running :
```bash
cd /var/www/html/
git clone http://git.laswitchtech.com/louis/vdism.git
```

Finally configure the software by editing your `config.php` file. And you are done.

```php
<?php
//LDAP Server IP and DOMAIN
$LDAPSRV="x.x.x.x";
$LDAPDN="DOMAIN";

//VDI Server IP, Username, Password, ssh-key file, Lowercase domain used with DNS and Watched port
$VDIHOST="x.x.x.x";
$VDIUSER="username";
$VDIPASS="password";
$VDIKEY="id_rsa.key";
$VDIDN="domain";
$VDIPORT="3389";
?>
```
