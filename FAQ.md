# F.A.Q.

#### Which Linux Distributions can be managet with UPM ?
It should be possible to manage any Distribution.

After installation UPM has a list of premade distribution configurations, those are currently:
* Debian
* Ubuntu
* CentOS
* Oracle Enterprise Linux

Other distribution configurations can be added. Should you have one, please send it to me and I will add them to the repository

#### Can Packages be [un-]installed? Can repositories be managed ?
No, UPM is solely for updatemangement! To manage packages etc. tools like Puppet can be used.

#### Can i run a specific command on a host?
In theory this should work. For example: you could change the command for the uptime query and then inventory the host. But beware! UPM is not designed for this and I strongly advise against it ! Again use Puppet / Bolt for these usecases.

#### Does UPM have a permissions system?
Currently UPM has no permission implementation. This means anyone with access to the webserver has full rights. For now use Basic authentification / .htaccess to manage who can use the page.

#### automatic apt update
The default UPM distribution configuration for Debian and Ubuntu does not do an apt update before testing for new updates. Without doing an apt update UPM is faster to inventory a host.
For automatic apt update use apt feature "periodic update package lists"
For example:
```
echo 'APT::Periodic::Update-Package-Lists "1";' > /etc/apt/apt.conf.d/02periodic_update_package_lists
```
With this apt update is daily triggered via cron.

#### How much ressources would a UPM Server requirere?
UPM is extremly lightweight. As for Storage only a few Megabytes for UPM and MariaDB are needed. Currently I use a virutal machine with 4 cores and 2 GB of RAM. This is enough to manage a few hundred hosts. 

#### How many hosts can be inventoried / updated at a time?
This depends on your webserver. UPM uses an Ajax Request for each task. Browsers currently allow between 6 and 8 paralel requests. The Webserver should be updated to http2, since it allows for around a hundred Ajax Requests to be excetuted simultaneously

Example for Dedbian 10:
```
apt install libapache2-mod-fcgid php-fpm
a2dismod php7.3 mpm_prefork
a2enmod mpm_event fcgid proxy_fcgi actions
a2enmod http2
a2enconf php7.3-fpm
```
My  `mpm_event.conf` , guaranteeing that the apache takes multiple requets at once :
```
  StartServers              4
  MinSpareThreads         100
  MaxSpareThreads         150
  ThreadLimit             300
  ThreadsPerChild         300
  MaxRequestWorkers       600
  MaxConnectionsPerChild    0
```

Lastly restart the Apache Service like this:
```
apache2ctl stop
apache2ctl start
```

The ProxyTimeout should be changed, since an Update could take multiple minutes. I use 30 minutes.
Change here: `/etc/apache2/mods-enabled/fcgid.conf`
`ProxyTimeout     1800`

Depending on the specifications of the Server different amounts of PHP Sessions can be processed by the UPM-Server.
I use the following with my Setup [4 Core, 2GB RAM]
`/etc/php/7.3/fpm/pool.d/www.conf`
```
pm = static
pm.max_children = 100
```
Of course restart after editing `systemctl restart php7.3-fpm.service`

Here an example inventorisation of 350 Hosts
http1.1 | http2 + mpm_event + php-fpm
------------ | -------------
156149 ms | 44829 ms


#### How long does it take to inventory a server ?
Of course this depends on your specs. Here an example from my server:
Debian based System | Redhat based System
------------ | -------------
1-2 seconds | 3-4 seconds

#### What are important updates?
Important updates are updates which you need to know about. This isn't a mail-notification, but a visual cue in the interface. If UPM detects an important update, it will be marked red. If you're working with multiple hosts at once, then hosts with an important update will be automatically deselected. This is a safeguard to stop you from unintionally updating critical systems. I use it for packages like Apache, MariaDB or similar packages, where a downtime would be necessary. You can add a comment to an important update
