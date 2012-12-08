<?php
/*/
type:       class
author:
  name:     Oliver Anan
  mail:     <oliver@ananit.de>
version:    [0,1,0,0]
tags:       [system core]
================================================================================
DefaultCore
================================================================================
A core with some essintial plugins already set.

The default co3config will use this core.

List of preset pluins:
--------------------------------------------------------------------------------
IO:         daliaIT\co3\IO\IOPlugin
loader:     daliaIT\co3\loader\LoaderPlugin
package:    daliaIT\co3\package\PackagePlugin

Source
--------------------------------------------------------------------------------
/*/
namespace daliaIT\co3;
use Spyc,
    OutOfRangeException,
    daliaIT\CoLoad\CoLoad,
    daliaIT\co3\IO\IOPlugin,
    daliaIT\co3\loader\LoaderPlugin,
    daliaIT\co3\package\Package,
    daliaIT\co3\package\PackagePlugin;
    
class DefaultCore extends Core{
    #:this
    public function boot($conf){
        $this->conf = $conf;
        $parser = new Spyc();
        $rawPackage = $parser->load(
            file_get_contents($this->conf['package']['location'])
        );
        $rawPackage = $rawPackage['value'];
        $this->setPlugin('loader',$this->createLoaderPlugin($rawPackage));        
        $this
            ->createIOPlugin($rawPackage)
            ->createPackagePlugin($rawPackage)
            ->loadDependencies();
    }
    
    #:LoaderPlugin
    protected function createLoaderPlugin($rawPackage){
        $src = (isset($rawPackage['src']))
            ? array($this->getConfValue('path/co3dir').'/'.$rawPackage['src'])
            : array();
        $loader = new CoLoad(
            $this->conf['path']['tmp'].'/classMap.json',
            $src
        );
        $loader->register();
        return LoaderPlugin::inject(array(
            'loaders' => array( 'main' => $loader)
        ));
    }
    
    #:this
    protected function createIOPlugin($rawPackage){
        $plugin =  new IOPlugin();
        $this->setPlugin( 'IO', $plugin);
        return $this;
    } 
    
    #:this
    protected function createPackagePlugin($rawPackage){
        $plugin = PackagePlugin::inject(array(
            'packageOptions'    => array('co3'=> PackagePlugin::LOAD_ALL),
            'filterClasses'     => $rawPackage['filters'],
            'pluginClasses'     => $rawPackage['plugins'],
            'packageSrc'        => array($this->getConfValue('path/package')),
            'encoding'          => $this->getConfValue('encoding')
        ));
        $this->setPlugin('package',$plugin);
        if(
            isset($rawPackage['resource']) 
            && $this->IO->filterExists('file')
        ){
            $plugin->getFilter('file')->addSource(
                $this->getConfValue('path/co3dir').'/'.$rawPackage['resource']
            );   
        }
        $package = Package::inject($rawPackage);
        
        $plugin->loadPackage(
            'co3', 
            $this->getConfValue('path/package'), 
            $package
        );
        return $this;
    }
    
    #:\daliaIT\co3\package\Package
    protected function getOwnPackage(){
        return $this->IO->in(
            file_get_contents($this->conf['package']['location']),
            $this->conf['package']['filter']
        );
    }
    
    #:this
    protected function loadDependencies(){
        $dependecies = $this->getConfValue('dependency');
        if(! $dependecies) return $this;
        foreach($dependecies as $dependency){
            $this->package->in($dependency);
        }
        return $this;
    }
    
    #:bool
    public function pluginExists( $name, $autoLoad=true ){
        if(isset($this->plugins[$name])){
            return true;
        } elseif(isset($this->plugins['package']) && $autoLoad){
            return (bool) $this->package->searchPlugin($name);
        } else {
            return false;
        }
    }
    
    #:Plugin
    public function getPlugin($name){
        if( !$this->pluginExists( $name, false ) ){
            $pluginClass = $this->package->searchPlugin($name);
            if(! $pluginClass){
                throw new  OutOfRangeException("Unkown Plugin '$name'");
            } else {
                $this->setPlugin( $name, new $pluginClass() );
            } 
        }
        return $this->plugins[$name];
    }
}