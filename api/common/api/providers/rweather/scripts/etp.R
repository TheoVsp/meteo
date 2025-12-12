#!/usr/bin/env Rscript
options(error=traceback)
options(digits.secs = 6)

print("Start script")
currentTsScript <-as.numeric(Sys.time())*1000
print(Sys.time())

args <- commandArgs(trailingOnly=TRUE)

if (length(args) == 3) {
  inputfile <- args[1]
  outputfile <- args[2]
  debug <- args[3]
}else{
  stop("Missing parameters", call.=FALSE)
}
print("------------------")
print("INPUTS")
print(paste("inputfile:", inputfile, sep=""))
print(paste("outputfile:", outputfile, sep=""))

print("------------------")
print("Loading Rweather library")
currentTs <- as.numeric(Sys.time())*1000
library(Rweather)
print(paste(c("Loaded Rweather library done (",round((as.numeric(Sys.time())*1000) - currentTs, digits = 0), " ms)"), collapse = ""))

print("------------------")
print("Rweather library version")
packageVersion("Rweather")

print("------------------")
currentTs <- as.numeric(Sys.time())*1000
print("Compute etp")
csv <- read.csv(inputfile)
dataset_calc <- as.data.frame(csv)
#if(debug){
#  library(skimr)
#  skim(dataset_calc)
#}
dataset <- etp$new(dataset_calc)
output <- dataset$compute()
#if(debug){
#  skim(output)
#}
print(paste(c("Compute etp done (",round((as.numeric(Sys.time())*1000) - currentTs, digits = 0), " ms)"), collapse = ""))

print("------------------")
print("RESULTS")
#print(paste("result:", output, sep=""))

print("------------------")
print("OUTPUT")
#print(mydf)
#print(paste("Output file : ", outputfile, sep=""))
write.csv(output, file = outputfile)
print(paste(c("OUTPUT done (",round((as.numeric(Sys.time())*1000) - currentTs, digits = 0), " ms)"), collapse = ""))

print("------------------")
print("End script")
print(Sys.time())
print(paste(c("Script done (",round((as.numeric(Sys.time())*1000) - currentTsScript, digits = 0), " ms)"), collapse = ""))