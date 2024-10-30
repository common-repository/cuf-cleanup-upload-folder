'use strict';


angular.module('cufPlugin')
    .controller('FilesCtrl', ['$scope', '$rootScope', 'FilesResource', 'STATUS','$timeout','$q',
        function ($scope, $rootScope, FilesResource, STATUS,$timeout,$q) {

            var refresh=true;
            $scope.status = STATUS;

            $scope.base = '';
            $scope.base = [];
            $scope.unverifieds=[];

            $scope.isEmpty=function(object){
                return _.isEmpty(object);
            }

            function resetOrdered() {
                $scope.orderedFiles = {};
                $scope.orderedFilesUnique = {};
                $scope.unattacheds=[];
                $scope.noting=-1;
            };

            resetOrdered();

            var files;

            $rootScope.$on('tabFiles', function () {
                $scope.directories = FilesResource.getAllDirectories().$promise.then(function (resultDirs) {
                    $scope.base = resultDirs.data.base;
                    $scope.dirs = resultDirs.data.dirs;

                });

            });

            //if options is send from the optionsCtrl
            $rootScope.$on('refresh', function (event) {

                refresh = true;


            });

            $scope.getChecks = function () {
                var syncCall = [];

                //i have to refresh the image tab
                if (refresh) {
                    refresh=false;
                    if (_.isUndefined($scope.htmlShortcodes)) {

                        if ($scope.options.shortCodeCheck) {
                            syncCall.push(FilesResource.shortcodes().$promise.then(function (htmlShortcodes) {

                                $scope.htmlShortcodes = htmlShortcodes.data;

                            }));
                        }
                    }

                }


                if (syncCall.length > 0) {
                    $q.all(syncCall).then(function () {
                        scanPathDir();
                    });
                } else {
                    scanPathDir();
                }


            };


            var verifyStatus = function (file) {

                if (file.status.used == STATUS.USED.UNUSED) {


                    if ($scope.options.deleteAttached || file.status.attach == STATUS.ATTACH.UNATTACH) {
                        file.toDelete = true;
                    } else {
                        file.toDelete = false;
                    }


                }
            };


            var orderFile = function (file) {
                if (!_.isUndefined(file.id) && !_.isNull(file.id)) {
                    if (_.isUndefined($scope.orderedFilesUnique[file.id]) && _.isUndefined($scope.orderedFiles[file.id])) {
                        $scope.orderedFilesUnique[file.id] = [];

                        $scope.orderedFilesUnique[file.id].push(file);

                    } else {

                        if (_.isUndefined($scope.orderedFiles[file.id])) {
                            $scope.orderedFiles[file.id] = $scope.orderedFilesUnique[file.id];
                            delete $scope.orderedFilesUnique[file.id];
                        }
                        if (file.status.attach === STATUS.ATTACH.ATTACH_ORIGINAL) {
                            if ($scope.orderedFiles[file.id].length > 1) {
                                $scope.orderedFiles[file.id] = [file].concat($scope.orderedFiles[file.id]);
                            }
                        } else {
                            $scope.orderedFiles[file.id].push(file);
                        }
                    }

                } else {

                    if (_.isUndefined($scope.unattacheds)) {
                        $scope.unattacheds = [];
                    }
                    $scope.unattacheds.push(file);
                }

            };


           function scanPathDir() {
                resetOrdered();
                if (!_.isUndefined($scope.pathDir) && $scope.pathDir != "") {

                    $scope.noting = 2;
                    FilesResource.getFilesFromDirectory({path: $scope.pathDir}).$promise.then(function (resultFiles) {

                        files = resultFiles.data;
                        if (!_.isUndefined(resultFiles.data) && _.size(resultFiles.data) > 0) {

                            var arrayFileName={};
                            $scope.unverifieds={};

                            var pathDir = $scope.pathDir;

                            var fileArray;
                            if(_.isObject(resultFiles.data)){
                                fileArray= _.values(resultFiles.data);
                            }else{
                                fileArray=resultFiles.data;
                            }


                            var iMax= Math.ceil(fileArray.length/ $scope.options.slice);

                            for(var i=0; i<iMax; i++){
                                var limit = (i+1)*$scope.options.slice;
                                limit=  fileArray.length > limit  ? limit :  fileArray.length;
                                var parts = fileArray.slice(i*$scope.options.slice,limit);
                                var names=[];
                                for(var p=0; p<parts.length;p++){
                                    names.push(parts[p].name);
                                    parts[p].status.used = STATUS.USED.ASKING;
                                    parts[p].status.attach = STATUS.ATTACH.ASKING;
                                    arrayFileName[parts[p].name]=parts[p];
                                }


                                FilesResource.verifyFiles({
                                    path: pathDir,
                                    names: names
                                }).$promise.then(function (resultVerify) {

                                        angular.forEach(resultVerify.data,function(statusAndId,name){
                                            arrayFileName[name].status=statusAndId.status;
                                            arrayFileName[name].id=statusAndId.id;

                                            for (var key in $scope.htmlShortcodes) {
                                                if ($scope.htmlShortcodes[key].indexOf(name) > -1) {

                                                    arrayFileName[name].status.used= STATUS.USED.USED;
                                                    break;
                                                }
                                            }


                                            verifyStatus(arrayFileName[name]);
                                            orderFile(arrayFileName[name]);

                                        });


                                    });

                            }
                            $scope.noting = 0;

                        } else {
                            $scope.noting = 1;
                        }
                    });
                }

            };


            $rootScope.$on('refreshDeleteButton', function () {
                angular.forEach(files, function (file) {
                    verifyStatus(file);
                });


            });

            var makeBackup = function (file) {
                //TODO:

                makeDelete(file);

            };

            var makeDelete = function (file) {
                console.log(file);
                FilesResource.deleteFileFromDirectory({
                    path: file.path,
                    name: file.name
                }).$promise.then(function(result){

                            file.toDelete=false;
                            file.status.attach=STATUS.ATTACH.DELETED_ATTACH;
                            file.status.inServer=STATUS.IN_SERVER.NOT_INSERVER;

                    });

            }

            $scope.deleteFile = function (file) {

                verifyStatus(file);

                if (file.status.attach==STATUS.ATTACH.ATTACH_ORIGINAL) {
                    //TODO: verify if original image, if original show popup, warning
                } else {
                    if ($scope.options.backup) {
                        makeBackup(file);
                    } else {
                        makeDelete(file);
                    }

                }

            };
        }]
);
