### Installation

安装包:
```shell script
composer require orzlee/api-assistant
```

发布配置文件
```
php artisan vendor:publish --provider="Orzlee\ApiAssistant\ApiAssistantServiceProvider"
```

### Configurations

修改`app\Exceptions\Handler.php`以接管API异常处理:
```php
    use Orzlee\ApiAssistant\Exceptions\ApiExceptionReport;

    public function render($request, Throwable $exception)
    {
        $reporter = ApiExceptionReport::make($exception);
        if ($reporter->shouldCatchException()) {
            return $reporter->report();
        }
        return parent::render($request, $exception);
    }
```
修改控制器文件

```php
use Orzlee\ApiAssistant\Traits\ApiResponse;
use Orzlee\ApiAssistant\Traits\Controller\QueryRequestFilter;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use ApiResponse, QueryRequestFilter;
}
```
修改模型文件
```php
use Orzlee\ApiAssistant\Traits\Model\AutoLoadRelation;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use AutoLoadRelation;
    
    /**
     * 允许过滤查询的字段，条件字段会匹配该字段，自动过滤掉不包含字段
     * 条件过滤查询也会通过该字段过滤
     * api http://xxx.com/your-api-url?fields=name,created_at&name=test
     * @return string[]
     */
    public static function fields() :array
    {
        return [
            'name',
            'avatar',
            'created_at',
        ];
    }

    /**
     * api url 参数中包含fields=my_post会自动加载关系
     * api http://xxx.com/your-api-url?fields=name,my_post
     * @return string[]
     */
    public static function relationFields() :array
    {
        return [
            'posts' => 'my_post',
        ];
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

}
```
在`config/api-assistant.php`中还有很多配置。

### Use

在控制器中使用:
```php
class PostController extends Controller{
    
    public function __construct(Request $request) {
        $this->init(Post::class, $request);
    }

    public function index(Request $request)
    {
        return $this->success(PostResource::collection($this->get()));
    }

    public function show($id)
    {
        return $this->success(new PostResource($this->query->findOrFail($id)));
    }

    public function update($id, CreatePostRequest $request)
    {
        $post = $this->query->where('user_id', $request->user()->id)->find($id);
        if (!$post) {
            return $this->failed('not found post.', 404);
        }
        $post->update($request->all());
        return $this->success(new PostResource($post));
    }
}
```
查询例子:
```
http://xxx.com/your-api-url?fields=id,name,status,created_at&status=!=,1&name=!=,null&orderBy=status,desc&page_size=2&page=2
http://xxx.com/your-api-url?name=like,~test~
http://xxx.com/your-api-url?status=1,or,2
http://xxx.com/your-api-url?status[]=1,or,2&status
//闭包查询，查询sql像这样： select * form post where (status = -1 or state = -2) and name = 'test' order by status;
http://xxx.com/your-api-url?closures[][status]=or,-1&closures[][state]=or,-2&name=test&orderBy=status
```

模型缓存:
```php
use \App\Traits\Model\HasCache;
use \Illuminate\Database\Eloquent\Model;
class Category extends Model
{
    use HasCache;
    
    /**
     * 自定义缓存和key
     * @return mixed
     */
    public static function getParentCategoryForCache()
    {
        return self::cache('parent_category', function (Category $model) {
            return $model->where('parent_id', 0)->get();
        });
    }
    /**
     * 不传入key值，使用默认key，但是会替换cache函数缓存
     * @return mixed
     */
    public static function getSubCategoryForCache()
    {
        return self::cache(function (Category $model) {
            return $model->where('parent_id', '>', 0)->get();
        });
    }
    
}
use Illuminate\Routing\Controller;
use \Orzlee\ApiAssistant\Traits\ApiResponse;
class CategoryController extends Controller
{
    use ApiResponse;
    public function categories()
    {
        return $this->success(Category::cache());
        //return $this->success(Category::getParentCategoryForCache());
        //清理缓存
        //(new Category)->forgetCache();
        //(new Category)->forgetCache('parent_category');
    }
}
```