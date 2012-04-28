#!/usr/bin/env python
# -*- coding: utf-8 -*-
# Скрипт для построения и сверки сигнатур структур сайтов.
# Перед использованием обязательно измените первые переменные (myMail, smtp_server,...,sendOk)
# Запуск: python md5-struct.py /dir/of/site /path/to/struct/file build|check
# Авторы: Антон Кузьмин | anton.kuzmin.russia@gmail.com | http://anton-kuzmin.blogspot.com
#         Алексей Мещеряков | tank1st99@gmail.com

import sys
import os
import hashlib
import re
import smtplib
from email.mime.text import MIMEText

def help():
    print "==========================================================================================="
    print "Script for build and check structure signatures of web-sites."
    print "Please, change source code (set variable - myMail, smtp_server, smtp_login,"
    print "smtp_mail_from, smtp_pass) before use."
    print "Usage: python md5-struct.py /dir/of/site /path/to/struct/file build|check"
    print "(c) Anton Kuzmin         | anton.kuzmin.russia@gmail.com | http://anton-kuzmin.blogspot.com/"
    print "(c) Alexey Meshcheryakov | tank1st99@gmail.com           | "
    print "============================================================================================"

def make_array_from_file (file):
    md5_files = {}
    for line in file:
	separator_pos = line.strip().find(' ')	
	hash = line.strip()[:separator_pos].strip()
	filename = line.strip()[separator_pos+1:].strip()
        md5_files[filename] = hash
    return md5_files

def build_md5_for_files(dirOfSite, ignoreRegexprs):
    md5_command_output = os.popen("find "+dirOfSite+" -type f -exec md5sum {} \;", "r")
    md5_files = make_array_from_file(md5_command_output)
    md5_command_output.close()
    return md5_files

# Address for reports
myMail = "aaa@bbb.com"
smtp_server = "smtp.bbb.com"
smtp_login = ""
smtp_pass = ""
smtp_mail_from = "aaa@bbb.com"
ignoreRegexprStrs = ['^.*\.mp3$']
sendOk = True

def main (argv = None):
    if argv is None:
        argv = sys.argv
    if len(argv) != 4:
        help()
        return
    
    dirOfSite = argv[1]
    pathToStructFile = argv[2]
    cmd = argv[3]
    ignoreRegexprs = []
    for regexprstr in ignoreRegexprStrs:
        ignoreRegexprs.append(re.compile(regexprstr))
    
    if not os.path.exists(dirOfSite):
        print "Directory "+dirOfSite+" not exists"
        return
    
    if cmd == 'build':
        try:
            struct_file = open(pathToStructFile, "w")
            md5_files = build_md5_for_files (dirOfSite, ignoreRegexprs)
            for hash_file in md5_files.keys():
                struct_file.write(md5_files[hash_file]+' '+hash_file+"\n")
        except IOError:
            print pathTOStructFile + " is not writeable"
            return
        else:
            struct_file.close()
    else:
        if not os.path.exists(pathToStructFile):
            print pathToStructFile+" is not exists"
        try:
            log_file = open(pathToStructFile, "r")
            log_array = make_array_from_file(log_file)
            check_array = build_md5_for_files (dirOfSite, ignoreRegexprs)
            changed_files = []
            
            for filename in log_array.keys():
                if check_array.has_key(filename):
                    if log_array[filename] != check_array[filename]:
                        changed_files.append(filename)
                    del(log_array[filename])
                    del(check_array[filename])
            
            msg_text = ''
            if len(changed_files) != 0:
                msg_text += "Changed Files:\n"
                for filename in changed_files:
                    msg_text += filename+"\n"
            if len(log_array) != 0:
                msg_text += "Deleted Files:\n"
                for filename in log_array.keys():
                    msg_text += filename+"\n"
            if len(check_array) != 0:
                msg_text += "New Files:\n"
                for filename in check_array.keys():
                     msg_text += filename+"\n"
	    msg = None
            try:
                if len(msg_text) != 0:
                    msg = MIMEText(msg_text)
                    msg['Subject'] = "ALERT"
                elif sendOk:
                    msg = MIMEText('OK')
                    msg['Subject']= "OK"
                if msg is not None:
                    msg['From'] = smtp_mail_from
                    msg['To'] = myMail
                    s = smtplib.SMTP()
                    s.connect(smtp_server)
                    s.login(smtp_login, smtp_pass)
                    s.sendmail(smtp_mail_from, [myMail], msg.as_string())
                    s.quit()
            except:
                print "SMTP error"
        except IOError:
            print pathTOStructFile + " is not readable"
            return
        else:
            log_file.close()
            

if __name__ == '__main__':
    main()
