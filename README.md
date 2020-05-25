# Warranty
I assume no liability if you destroy your server with UPM!
Do not use UPM in production environment!
UPM is work in progress and in alpha state.
I use UPM in a production environment with hundreds of servers (Debian, Ubuntu, CentOS and Oracle Enterprise Linux) for over a year now, but I know it like the back of my hand

# Short description
UPM (universal patch manager) is an agentless, simple and lightweight solution to manage your server updates. UPM can manage any linux distribution as well as any amount of servers.
UPM uses ssh public key authentification to access a server and simple bash commands to do the tasks.
you can update a single server or a whole set of servers at once.
Please also read the 
### [F.A.Q.](FAQ.md)


# Requirements
For running UPM you need a webserver with php7.X and a MariaDB database.
You also need the php library phpseclib (> 2.0) and mysql.
You can start with 1 CPU and 1GB Ram to manage < 100 hosts. With 4 CPU and 2GB Ram you can manage up to 1000 hosts with good performance. Please see the FAQ for more informations about requirements and performance.

# Installation
You need a working webserver with php7.X
Install the php librarys phpseclib and mysql.

Debian based systems

`apt install php-phpseclib php-mysql`

### Create a MariaDB database and user
```
CREATE DATABASE upm;
CREATE USER upm@localhost IDENTIFIED BY 'SecurePassword';
GRANT ALL PRIVILEGES ON upm.* TO 'upm'@'localhost';
FLUSH PRIVILEGES;
```

### Clone git repository to htdocs folder of the webserver.
```
git clone https://github.com/schirrmie/upm.git /var/www/html
cd /var/www/html
```

### import MariaDB database
`mysql -u upm -p upm < upm.mysql`

### set database access for upm
```
mv config.php.in config.php
vim config.php
```
set database name, username and password what you created

### test accessing UPM in the browser
Now you should be able to see the UPM site.
On the top right side press the button "Global Settings". you should see existing distribution configs for Debian, Ubuntu, OracleServer, etc.
You should also see default distritubion command and distribution version command.
If you see all this then you're ready to start otherwise look for any errors in your webserver error log.

### UPM host access setup
Now we need to setup our UPM installation to access our hosts.
UPM uses ssh public key to access the hosts and run commands.
You can set the login settings (username, ssh port, ssh key) at 3 levels
- globaly under "Global Settings",
- folder based 
- server based

When UPM access a server it looks for login setting at the server, if server settings are empty it will use the folder settings and if folder settings are empty the global settings will be used.

#### generate an ssh key pair
`ssh-keygen -m PEM -t rsa -b 4096 -f /root/update_agent -C "update_agent"`
- old version of phpseclib only support old key format.
- phpseclib <= 2.0 does not support ecdsa or ed25519 keys.
- do not set any password!

#### set private key in UPM
copy the private key

`cat /root/update_agent`

Set the private key in UPM. For the first try set the ssh private key under "Global Settings".
Also set the ssh port (22) and a login username.

### Host setup
Now its time prepare your first host .

#### add user
On your host create a new user for UPM. you need a home directory for the ssh private key. For the shipped distribution commands the user need bash as shell (sh is the default shell on most distribution)

`useradd update_agent -m -s /bin/bash`

#### set ssh public key
put the ssh public key under /home/update_agent/.ssh/authorized_keys

`mkdir /home/update_agent/.ssh`
`echo "SSH public key" > /home/update_agent/.ssh/authorized_keys`

Set ownership and rights
```
chown -R update_agent: /home/update_agent/.ssh
chmod 700 /home/update_agent/.ssh
chmod 600 /home/update_agent/.ssh/authorized_keys
```

#### set sudo config for the new user
Debian based systems

```
echo "Defaults:update_agent env_keep=DEBIAN_FRONTEND" > /etc/sudoers.d/10_update_agent
echo "update_agent ALL=NOPASSWD: /usr/bin/apt, /usr/bin/apt-get" >> /etc/sudoers.d/10_update_agent
```

RedHat based systems

`echo "update_agent ALL=NOPASSWD: /usr/bin/yum" > /etc/sudoers.d/10_update_agent`

If you want to use UPM reboot feature you need sudo for shutdown too:

`echo "update_agent ALL=NOPASSWD: /sbin/shutdown" >> /etc/sudoers.d/10_update_agent`

For detecting the right distribution you need lsb_release on the host.
Debian based systems

`apt install lsb-release`

RedHat based systems

`yum install redhat-lsb-core`

## Testing
Now you are ready to test your first host in UPM
Access UPM
Add a new server, select the new server, press "Inventory".
If all is right you should see the distribution, uptime and the updates the host have. you can try to press "Update all" and watch the server output.
If not look for any errors. Good Luck!

# Screenshots
Serverlist view
![folder](https://user-images.githubusercontent.com/7531415/79869948-7959d780-83e2-11ea-8a75-79d48a263d6f.png)

Server view
![server](https://user-images.githubusercontent.com/7531415/79869956-7ced5e80-83e2-11ea-92f5-d218d7d0b871.png)
