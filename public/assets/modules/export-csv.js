(function(Highcharts){var each=Highcharts.each;Highcharts.Chart.prototype.getCSV=function(){var columns=[],line,tempLine,csv="",row,col,options=(this.options.exporting||{}).csv||{},dateFormat=options.dateFormat||'%Y-%m-%d %H:%M:%S',itemDelimiter=options.itemDelimiter||',',lineDelimiter=options.lineDelimeter||'\n';each(this.series,function(series){if(series.options.includeInCSVExport!==false){if(series.xAxis){var xData=series.xData.slice(),xTitle='X values';if(series.xAxis.isDatetimeAxis){xData=Highcharts.map(xData,function(x){return Highcharts.dateFormat(dateFormat,x)});xTitle='DateTime'}else if(series.xAxis.categories){xData=Highcharts.map(xData,function(x){return Highcharts.pick(series.xAxis.categories[x],x)});xTitle='Category'};columns.push(xData);columns[columns.length-1].unshift(xTitle)};columns.push(series.yData.slice());columns[columns.length-1].unshift(series.name)}});for(row=0;row<columns[0].length;row++){line=[];for(col=0;col<columns.length;col++)line.push(columns[col][row]);csv+=line.join(itemDelimiter)+lineDelimiter};return csv};if(Highcharts.getOptions().exporting)Highcharts.getOptions().exporting.buttons.contextButton.menuItems.push({text:Highcharts.getOptions().lang.downloadCSV||"Download CSV",onclick:function(){Highcharts.post('http://www.highcharts.com/studies/csv-export/csv.php',{csv:this.getCSV()})}})}(Highcharts));;