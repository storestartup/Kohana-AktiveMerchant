from __future__ import with_statement
from fabric.api import *

env.user='andrew'
env.hosts=['166.78.165.253','direct.storestartup.com']
env.password='Bl0y3a'

def deploy():
	with cd('/var/www/kohana/modules/aktivemerchant'):
		run('git pull')
