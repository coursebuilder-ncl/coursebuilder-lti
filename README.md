# NCL CourseBuilder LTI Tool

The NCL CourseBuilder LTI tool provides a simple to use interface for instructors to upload content to a VLE to be automatically converted into accessible and flexible documents with NCL CourseBuilder. With this tool, the instructor does not need to have any knowledge of running Unix software or uploading content to a web-server in order to share the web-based output generated by CourseBuilder.

## Installation

### Prerequisites
  * A web server supporting PHP such as Apache or nginx. PHP must be configured to allow calling the `exec()` function
  * Docker
  * Account details for write access to an empty MySQL/SQLite database

### LTI Tool Setup
First, extract the software into an install directory (referred to as `INSTALLDIR`) visible to your web server. Copy the file `config.dist.php` to `config.php` and then edit the file to include your own site settings. At least the following variables should be edited,

Name         | Description | Example
-------------|-------------|---------
`WEBDIR`     | The intended web path for accessing the tool | `/lti`
`INSTALLDIR` | The full file system path to the tool's install directory | `/var/www/webroot/lti`
`PROCESSUSER` | The "processing user" that will run the CourseBuilder build tools via docker  | `programs`
`DB_NAME` | A database DSN specifying a database host and name  | `mysql:dbname=coursebuilder;host=database.example.com`
`DB_USERNAME` | Database username  |
`DB_PASSWORD` | Database password  |

### User Permission Setup

Uploaded documents are compiled by running the CourseBuilder software in a docker container. Since allowing access to docker is equivalent to giving root access to a user, this must be handled carefully. For separation of privileges it is recommended to create an entire new Unix user to be used as the CourseBuilder processing user with access to Docker.

Assuming your processing user will be named `programs`, the following commands creates the user and adds it to the docker group,
```
# adduser programs
# usermod -aG docker programs
```

### Directory Permission Setup

For security, the user running the web server process should be able to write to `UPLOADDIR` and `PROCESSDIR/logs`, but not be allowed to write to anything else in `INSTALLDIR`. As such, ensure directory permissions are setup as follows, where `programs` is your processing user and `www-data` is the primary unix group for the user running the web server process.

Directory | Mode | Owner:Group
----------|------|--------------
`INSTALLDIR` | 755 | `programs:programs`
`UPLOADDIR`  | 775 | `programs:www-data`
`CONTENTDIR`  | 775 | `programs:www-data`
`PROCESSDIR/logs` |775 | `programs:www-data`

### Sudo Permission Setup

The `sudo` command should be configured so that the user running the web server process (e.g. `www-data`) can start the CourseBuilder processing script as the processing user and set some environment variables. This can be setup by adding the following line to the `/etc/sudoers` file,

```
www-data ALL = (programs) NOPASSWD: [PROCESSDIR]/process.sh
Defaults env_keep += "PROCESSDIR"
Defaults env_keep += "CONTENTDIR"
Defaults env_keep += "UPLOADDIR"
Defaults env_keep += "DOCKER_PROCESSING_VOLUME"

```

replacing `[PROCESSDIR]` with the full path to the processing directory.

### Docker Setup

Prepare the docker daemon by pulling the NCL CourseBuilder image. For example, using the processing user run the command:
```
$ docker pull coursebuilder/coursebuilder-docker:latest
```

### Admin Directory Setup

Apache rewriting should be used to direct all requests of the form `lti/content/[...]` to `lti/index.php?req_content=[...]`. In addition, the following directories should be made forbidden to the public:

 * `UPLOADIR`
 * `PROCESSDIR`
 * `INSTALLDIR/lib`
 * `INSTALLDIR/admin`

The path `WEBPATH/admin` should be protected for only server administrator access. An example Apache setup using Basic Authentication is shown below.

```
        <Location />
	    Require all granted
            DirectorySlash Off
            RewriteEngine on
            RewriteRule /lti/content/(.*)$ /lti/index.php?req_content=$1 [L,QSA]
        </Location>
        <Location /lti/upload>
	    Require all denied
        </Location>
        <Location /lti/process>
	    Require all denied
        </Location>
        <Location /lti/lib>
	    Require all denied
        </Location>
        <Location /lti/admin>
	    AuthType Basic
	    AuthName "Restricted admin section"
	    AuthUserFile /etc/apache2/.htpasswd
	    Require user admin
        </Location>
```

In this example a username and password for admin access can be setup by running:

```
# htpasswd /etc/apache2/.htpasswd admin
```

## LTI Setup

To use the NCL CourseBuilder LTI tool with your VLE, a VLE administrator will need to add the tool to your own instance of the VLE. To do this, the VLE needs to be setup as a tool consumer in the admin panel accessible on your web server at https://coursebuilder.example.com/lti/admin/.

Create a Name, Key, and Secret for your VLE using the *Add New Consumer* form on the admin page, and then forward that information on to your VLE administrator to be added as an external LTI tool. They will also need the URL for the LTI configuration XML file: https://coursebuilder.example.com/lti/xml/config.xml.

## Build Process Information

When content is uploaded to the LTI tool for processing, the following series of steps occurs.

  * First, a file is uploaded to the `UPLOADDIR` by the web server process. On upload a GUID is generated for the uploaded bundle of files. `.zip` files are extracted in-place.

  * The processing script is started. The script runs partially in a container, and so `sudo` is used to start the script as the processing user with access to Docker.

  * The content is copied from the `UPLOADDIR` directory to the `PROCESSDIR` directory.

  * When uploading raw `.tex` or `.md` files a standalone CourseBuilder compatible `course.yml` file is created. If a CourseBuilder `config.yml` file already exists as part of the upload, it is modified to inject the correct content web path and GUID.

  * CourseBuilder is run in a Docker container on the newly prepared `course.yml` in the `PROCESSDIR` directory.

  * If successful, the output is copied from the `PROCESSDIR` directory into the `CONTENTDIR` directory with the required directory permissions.

  * Clean up is performed and the copy of the uploaded files in `PROCESSDIR` are deleted.

  * Finally, the output of the process script is written to a log file at `PROCESSDIR/logs/<guid>.log`.

The `UPLOADDIR` directory should be emptied periodically to avoid filling the disk. For example, this can be handled by adding a cronjob for the `programs` user.
