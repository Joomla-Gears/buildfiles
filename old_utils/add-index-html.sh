#!/bin/bash
for d in `find component -type d | grep -v '.svn'`; do cp -f index.html $d; done
for d in `find modules -type d | grep -v '.svn'`; do cp -f index.html $d; done
for d in `find plugins -type d | grep -v '.svn'`; do cp -f index.html $d; done

for d in `find koowa/ -type d | grep -v '.svn'`; do cp -f index.html $d; done
