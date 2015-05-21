#!/bin/bash
#
##########################################################################################
#
# name:  dog.sh
# brief: monitor the running program.
# author:  Robbinson
# date:  2007-09-24
# args:
#
##########################################################################################


##########################################################################################
#
# brief: If the program is running
# pram:  $1: a command line from the dog.cnf
# retval: 0--exit, 1--running, 2--killed
#
##########################################################################################
is_running( )
{
 exe_file=`echo $line | awk '{print $1}'`
 
 pid=`ps -e -o pid,args | grep $exe_file | grep -v grep | awk '{print $1}'`
 if [ "$pid" != "" ]
 then
  if [ "$is_kill" -eq "1" ]
  then
   kill -9 $pid
   return 2
  else
   return 1
  fi
 fi

 return 0
}


##########################################################################################
#
# brief: starup the program
# pram:  $1: a command line from the dog.cnf
# retval: 0--OK, 1--Fail
#
##########################################################################################
start_up( )
{
 exe_file=`echo $line | awk '{print $1}'`
 count=`echo $exe_file | awk -F/ '{print NF}'`
 count=`expr $count - 1`
 path=`echo $exe_file | cut -d/ -f1-$count`

 logger "$line is not running,starting $line."
 /usr/bin/printf "%b" "***** dog.sh *****\n\nHostname: `hostname`\nProgram:$line\n\n$line is not running,start $line.\n\nDate/Time: `/bin/date
 '+%Y-%m-%d %H:%M:%S'` \n" | /bin/mail -s "dog.sh start $line" root@host.com
 cd $path
 ulimit -c unlimited
 $line
}


##########################################################################################
#
# brief: Guard all process from the dog.cnf
# config file dog.cnf:
# # command
# /home/test/smtpserver -d
# /home/test/pop3server -d
# /home/test/imailserver -d
# /home/test/apache/bin/httpd start
#
##########################################################################################
guard_process( )
{
 is_kill=0

 while read line
 do
  case $line in
  \#*)
   ;; # ignore any hash signs
  *)
   is_running $line, $is_kill
   if [ $? = 0 ]
   then
    start_up $line
   fi
   ;;
  esac
 done < $dog_cnf
}


##########################################################################################
#
# brief: List all process from the dog.cnf
#
##########################################################################################
list_process( )
{
 index=0
 is_kill=0

 echo "index   status   line"
 echo "-------------------------------------------------------------------------------"

 while read line
 do
  case $line in
  \#*)
   ;; # ignore any hash signs
  *)
   st_des="run"
   is_running $line, $is_kill
   if [ $? = 0 ]
   then
    st_des="exit"
   fi

   index=`expr $index + 1`
   echo "$index       $st_des     $line"
   ;;
  esac
 done < $dog_cnf
}


##########################################################################################
#
# brief: List the operate command
#
##########################################################################################
list_command( )
{
 echo ""
 echo "-------------------------------------------------------------------------------"
 echo "Command:"
 echo "  index---restart the specified process by index, index is 1, 2, 3, ..."
 echo "  l----list the all process"
 echo "  e----edit the config file by vi"
 echo "  q----quit the dog"
 echo "Please input:"
}


##########################################################################################
#
# brief: restart the specified process
#
##########################################################################################
restart_process( )
{
 restart_index=0
 is_kill=1
 is_restart=0

 while read line
 do
  case $line in
  \#*)
   ;; # ignore any hash signs
  *)
   restart_index=`expr $restart_index + 1`

   if [ "$command" = "$restart_index" ]
   then
    is_running $line, $is_kill
    if [ $? != 1 ]
    then
     start_up $line
     is_restart=1
     break
    fi
   fi
   ;;
  esac
 done < $dog_cnf

 if [ "$is_restart" = "1" ]
 then
  echo "restart OK!"
 else
  echo "$command is not invalid index!"
 fi
}


##########################################################################################
#
# brief: Manage the process
#
##########################################################################################
manage_process( )
{
 index=0
 list_process $dog_cnf, $index
 list_command

 while read command
 do
  case $command in
  q)
   echo "bye!!!"
   break;
   ;;
  e)
   vi $dog_cnf
   list_process $dog_cnf, $index
   list_command
   ;;
  l)
   list_process $dog_cnf, $index
   list_command
   ;;
  *)
   restart_process $dog_cnf, $command
   list_command
   ;;
  esac
 done
}


##########################################################################################
#
# brief: Print the help
#
##########################################################################################
usage( )
{
 echo "------------------dog.sh-----------------------"
 echo "  -h----print the help"
 echo "  -v----print the help"
 echo "  -e----edit the config file by vi"
 echo "  -d----guard all processes in background"
 echo "  -f----set the config file"
 echo "  -s----the second to sleep"
 echo "  examples:"
 echo "    1. guard all processes:"
 echo "       nohup dog.sh -d &"
 echo "       nohup dog.sh -d -s 60 &"
 echo "    2. list all processes:"
 echo "       dog.sh"
}


##########################################################################################
#
# brief: The main module
#
##########################################################################################
is_guard=0
sleep_sec=30
dog_cnf=./dog.cnf

while getopts hveds:f: option
do
 case $option in
 h)
  usage
  exit 0 ;;
 v)
  usage
  exit 0 ;;
 e)
  vi $dog_cnf
  ;;
 d)
  is_guard=1 ;;
 s)
  sleep_sec=$OPTARG ;;
 f)
  dog_cnf=$OPTARG ;;
 esac
done

if [ "$is_guard" = "0" ]
then
 manage_process $dog_cnf
else
 while [ "1" = "1" ]
 do
  guard_process $dog_cnf
  sleep $sleep_sec
 done
fi


# the end of the shell