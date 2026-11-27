#!/usr/bin/env python3
"""Enroll a reviewed trust record, or preflight native provisioning with SELECT/SHOW only. Never executes PHP/mutating SQL or reads .env."""
import argparse, hashlib, json, os, pathlib, platform, stat, subprocess, pwd, shlex

p=argparse.ArgumentParser(description=__doc__)
p.add_argument('--manifest',required=True)
p.add_argument('--sha256',required=True,help='Independently reviewed exact manifest digest')
p.add_argument('--pid',required=True,type=int)
p.add_argument('--port',required=True,type=int)
p.add_argument('--preflight', action='store_true', help='Read-only check before native schema provisioning; never enrolls or executes mutating SQL')
p.add_argument('--socket', help='Independently selected absolute UNIX socket (preflight only)')
p.add_argument('--pid-file', help='Independently selected absolute PID file (preflight only)')
p.add_argument('--owner', help='Expected OS process owner and MariaDB socket-auth administrator (preflight only)')
p.add_argument('--client', default='mariadb', help='Native MariaDB client executable (preflight only)')
a=p.parse_args()
assert a.preflight or os.geteuid()==0, 'Enrollment requires the independent OS administrator; application stays unprivileged.'
raw=pathlib.Path(a.manifest).read_bytes()
assert len(raw)<=16384 and hashlib.sha256(raw).hexdigest()==a.sha256, 'Reviewed manifest changed.'
m=json.loads(raw)
assert m['schema']=='rise-local-instance-v2'
assert set(m)=={'schema','application_root','runtime_datadir','runtime_server_id','scopes'}
root=pathlib.Path(m['application_root']);data=pathlib.Path(m['runtime_datadir'])
assert str(root.resolve())==str(root) and root.is_dir()
assert data.resolve().is_dir() and str(data.resolve())==str(data)
assert isinstance(m['runtime_server_id'],str) and m['runtime_server_id'].isdigit()
assert isinstance(m['scopes'],list) and 1<=len(m['scopes'])<=8
for scope in m['scopes']:
    assert set(scope)=={'environment','connection','database'}
    assert scope['environment'] in ['local','testing','candidate','preview'] and scope['connection']=='mysql'
    assert scope['database'] in ['rise_b1_candidate','rise_b1_test']
command=subprocess.check_output(['/bin/ps','-p',str(a.pid),'-o','command='],text=True).strip()
# These expected parameters come from reviewed fixture/provisioner evidence, never application .env.
assert 'mariadbd' in command and '--datadir='+str(data) in command
assert '--port='+str(a.port) in command and '--server-id='+m['runtime_server_id'] in command
if a.preflight:
    # Same reviewed manifest and process identity as enrollment; no trust record is written here.
    assert a.socket and a.pid_file and a.owner, 'Explicit socket, PID file and owner required.'
    owner=pwd.getpwnam(a.owner)
    assert owner.pw_uid == os.geteuid() != 0, 'Run preflight as the intended unprivileged instance owner.'
    sock=pathlib.Path(a.socket); pidfile=pathlib.Path(a.pid_file)
    assert sock.is_absolute() and pidfile.is_absolute()
    assert not sock.is_symlink() and stat.S_ISSOCK(sock.stat().st_mode) and sock.stat().st_uid==owner.pw_uid
    assert not pidfile.is_symlink() and pidfile.is_file() and pidfile.stat().st_uid==owner.pw_uid
    assert pidfile.read_text().strip()==str(a.pid), 'PID file mismatch.'
    assert data.stat().st_uid==owner.pw_uid, 'Datadir owner mismatch.'
    uid=subprocess.check_output(['/bin/ps','-p',str(a.pid),'-o','uid='],text=True).strip()
    assert int(uid)==owner.pw_uid, 'Process owner mismatch.'
    args=shlex.split(command)
    for expected in ['--datadir='+str(data), '--port='+str(a.port), '--server-id='+m['runtime_server_id'],
                     '--socket='+str(sock), '--pid-file='+str(pidfile), '--bind-address=127.0.0.1']:
        assert expected in args, 'Process argument mismatch: '+expected
    query="SELECT @@socket,@@pid_file,@@datadir,@@port,@@server_id,CURRENT_USER(),@@general_log;"
    query+="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('rise_b1_candidate','rise_b1_test');"
    query+="SHOW GRANTS FOR CURRENT_USER;"
    result=subprocess.run([a.client,'--no-defaults','--protocol=SOCKET','--socket='+str(sock),
                           '--user='+a.owner,'--batch','--skip-column-names','--execute='+query],
                          capture_output=True,text=True,timeout=15)
    assert result.returncode==0, 'Socket administrator identity query failed; stop before SQL provisioning.'
    lines=result.stdout.splitlines(); actual=lines[0].split('\t')
    assert len(actual)==7
    assert pathlib.Path(actual[0])==sock and pathlib.Path(actual[1])==pidfile
    assert pathlib.Path(actual[2]).resolve()==data and actual[3:5]==[str(a.port),m['runtime_server_id']]
    assert actual[5]==a.owner+'@localhost' and actual[6]=='0', 'Unexpected DB administrator or general log enabled.'
    assert lines[1:] and all(line.startswith('GRANT ') for line in lines[1:]), 'Target schema already exists; do not adopt existing data.'
    assert any(line.startswith('GRANT ALL PRIVILEGES ON *.* TO ') for line in lines[1:]), 'Schema provisioning administrator required.'
    print('PASS: reviewed socket/PID/datadir/port/server_id/owner, socket-auth administrator and absent target schemas; SELECT/SHOW only. No enrollment written.')
    raise SystemExit(0)
directory={'Darwin':'/Library/Application Support/RiseOpenCore/instance-manifests','Linux':'/etc/rise-open-core/instance-manifests'}[platform.system()]
directory=pathlib.Path(directory)
for parent in reversed([directory,*directory.parents]):
    if not parent.exists(): parent.mkdir(mode=0o755)
    s=parent.lstat()
    assert stat.S_ISDIR(s.st_mode) and not parent.is_symlink() and s.st_uid==0 and not s.st_mode&0o022, 'Untrusted ancestor; no permission changes performed.'
target=directory/(hashlib.sha256(str(root).encode()).hexdigest()+'.json')
# Never replace an existing installation pin. Recovery/rotation is an explicit administrator operation.
fd=os.open(target,os.O_CREAT|os.O_EXCL|os.O_WRONLY|os.O_NOFOLLOW,0o644)
with os.fdopen(fd,'wb') as f:
    f.write(raw);f.flush();os.fsync(f.fileno())
print('Enrolled:',target)
print('SHA256:',hashlib.sha256(raw).hexdigest())
