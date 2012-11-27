#!/bin/bash
cd /opt/inverter/
while :
do
service bluetooth restart
sleep 10
hcitool scan
sleep 10
bluetooth-agent 1234 00:12:06:15:10:43 &
sleep 10
rfcomm connect 0 00:12:06:15:10:43 1 &
sleep 10
perl inverter.pl
hcitool dc 00:12:06:15:10:43
sleep 10
done