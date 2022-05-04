# yii2-lazy-cache
Lazy cache functions

## Usage

Attach **Lazy** trait to your class

```php
<?php

class HeavyJob {
    
    use \prowebcraft\yii2lazycache\Lazy;

}
```

Wrap heavy data in lazy function

```php

$rocketToMarsTrajectory = $this->lazyWithCache('mars.trajectory', function() {
    // this function will be called once a day
    $trajectory = null;
    // heavy calculation here
    return $trajectory;
});

```