# PHP VPK Reader
VPK archive reader in PHP
  
### Reading file contents
```php
$vpk_file = 'package_dir.vpk';
$vpk = new VPKReader\VPK($vpk_file);
$data = $vpk->read_file('/path/to/file.txt', 10000);
echo $data;
```

### Getting directory tree
```php
$vpk_file = 'package_dir.vpk';
$vpk = new VPKReader\VPK($vpk_file);
$ent_tree = $vpk->vpk_entries

$print_tree = function($node, $pwd='') use (&$print_tree){
        if(!is_null($node) && count($node) > 0) {
                if(is_array($node)){
                        echo '<ul>';
                        foreach($node as $name=>$subn) {
                                $fp = "$pwd/$name";
                                echo "<li>$fp";
                                $print_tree($subn, $fp);
                                echo '</li>';
                        }
                        echo '</ul>';
                }else{ // Node
                        echo " | size: $node->size bytes";
                }
        }
};
$print_tree($ent_tree);
```
