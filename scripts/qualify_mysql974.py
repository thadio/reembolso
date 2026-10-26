#!/usr/bin/env python3
"""Canonical migration on synthetic MySQL9.7.4; never loads source .env or seeds people."""
import io
import argparse
import hashlib
import json
from pathlib import Path
import secrets
import shutil
import subprocess
import tarfile
import tempfile
import time

parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--run-synthetic',action='store_true')
parser.add_argument('--output',type=Path,required=True)
args=parser.parse_args()
if not args.run_synthetic:parser.error('Explicit --run-synthetic required')
SOURCE=Path(__file__).resolve().parents[1]
OUT=args.output
if not OUT.is_absolute() or OUT.exists() or SOURCE==OUT or SOURCE in OUT.parents or OUT.parent.resolve()!=OUT.parent:
    parser.error('New canonical output directory outside source required')
OUT.mkdir(mode=0o700)
PIN='mysql:9.7.4@sha256:8fb11c38b3551e22d50adbb688f027f8ff4ea2d2445aa277c1cc97b4af85ec54'
OWNER=secrets.token_hex(16);NAME='reembolso-mysql974-'+OWNER[:12]
ROOT=Path(tempfile.mkdtemp(prefix='reembolso-mysql974-'));ROOT.chmod(0o700)
CID=None
run=lambda args,**kw:subprocess.run(args,capture_output=True,text=True,check=True,timeout=240,**kw)
report={'scope':'Synthetic fresh schema, canonical migrate twice; no production/env/seed','version':'9.7.4','image':PIN,'source':run(['git','-C',str(SOURCE),'rev-parse','HEAD']).stdout.strip(),'owner':OWNER}
try:
    context=json.loads(run(['docker','context','inspect']).stdout)[0]
    assert context['Endpoints']['docker']['Host'].startswith('unix://')
    run(['docker','image','inspect',PIN])
    archive=subprocess.check_output(['git','-C',str(SOURCE),'archive','HEAD'])
    with tarfile.open(fileobj=io.BytesIO(archive)) as tar:
        for member in tar:
            path=Path(member.name)
            assert not path.is_absolute() and '..' not in path.parts
            if path.name == '.env':continue
            assert not member.issym() and not member.islnk()
            target=ROOT/path
            if member.isdir():target.mkdir(parents=True,exist_ok=True)
            elif member.isfile():
                target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(tar.extractfile(member).read())
    (ROOT/'db/migrate.php').write_bytes((SOURCE/'db/migrate.php').read_bytes())
    report['reviewed_migrator_sha256']=hashlib.sha256((SOURCE/'db/migrate.php').read_bytes()).hexdigest()
    password=secrets.token_hex(24)
    env=ROOT/'mysql-private.env';env.write_text('MYSQL_ROOT_PASSWORD='+password+'\nMYSQL_ROOT_HOST=%\nMYSQL_DATABASE=qualify974\n');env.chmod(0o600)
    CID=run(['docker','run','-d','--pull=never','--name',NAME,'--label','com.thadio.mysql974-audit='+OWNER,'--network','none','--cpus=1','--memory=768m','--tmpfs','/var/lib/mysql:rw,size=768m','--env-file',str(env),PIN,'--skip-log-bin','--innodb-buffer-pool-size=64M']).stdout.strip()
    # PHP is given only this owned DB loopback, through the fixture's network namespace.
    for _ in range(120):
        r=subprocess.run(['docker','exec',CID,'sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h127.0.0.1 -uroot -N -e "SELECT VERSION()"'],capture_output=True,text=True,timeout=5)
        if r.returncode==0:break
        time.sleep(.5)
    assert r.returncode==0 and r.stdout.strip()=='9.7.4'
    appenv='NAME=Qualification\nBASE_URL=http://127.0.0.1\nAPP_ENV=testing\nAPP_DEBUG=0\nTIMEZONE=UTC\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_NAME=qualify974\nDB_USER=root\nDB_PASS='+password+'\nDB_CHARSET=utf8mb4\n'
    (ROOT/'.env').write_text(appenv);(ROOT/'.env').chmod(0o600)
    php='retrato/ci-php:8.5.12-3ed93f28557f'
    for phase in ['first','repeat']:
        args=['docker','run','--rm','--pull=never','--network','container:'+CID,'--label','com.thadio.mysql974-audit='+OWNER,'--mount','type=bind,source='+str(ROOT)+',target=/fixture','--workdir','/fixture','--entrypoint','php',php,'db/migrate.php']
        result=subprocess.run(args,capture_output=True,text=True,timeout=240)
        (OUT/('reembolso-migrate-'+phase+'.log')).write_text(result.stdout+result.stderr)
        assert result.returncode==0 and 'Migrations concluídas.' in result.stdout,phase+'_migration_failed'
        report[phase]={'exit_code':result.returncode,'migrated':result.stdout.count('[ok]'),'skipped':result.stdout.count('[skip]')}
    report['passed']=True
except Exception as e:
    report['passed']=False;report['error']=type(e).__name__+':'+str(e)
finally:
    if CID:
        info=json.loads(run(['docker','inspect',CID]).stdout)[0]
        assert info['Config']['Labels'].get('com.thadio.mysql974-audit')==OWNER
        run(['docker','rm','-f','-v',CID]);report['owned_container_removed']=True
    shutil.rmtree(ROOT);report['owned_credentials_removed']=True
    (OUT/'reembolso-mysql974.safe.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report))
raise SystemExit(0 if report.get('passed') else 1)
