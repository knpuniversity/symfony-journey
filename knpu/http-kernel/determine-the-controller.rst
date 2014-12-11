Finding and Instantiating the Controller
========================================

Ok guys, we made it through the routing layer! Looking back, all it really
did was add an array with a few items to the ``$request->attributes``. The
*really* important thing it added is ``_controller``, and that's a string
that points to the class and function of our controller. But it *also* added
the value of each wildcard that's in our route::

    // effectively, RouterListener does this
    $request->attributes->add(array(
        '_controller' => 'AppBundle\Controller\DinosaurController::showAction',
        'id'          => 22,
        '_route'      => 'dinosaur_show',
    ));

Now, Symfony needs to figure out which controller to execute for this request.
Wait, isn't that what the ``_controller`` value is? Yes! Wait, no, not exactly.
That's a string, and while it *looks* like a function, it's not technically
a "callable" thing yet. You'll see what I mean.

The Controller Resolver
-----------------------

Symfony figures out which controller function to call for the request by
using something called the ``ControllerResolver`` and calling ``getController``.
Ready to go one step deeper into the core? Great - let's open this class up.
Oh, and there are *two* classes called ``ControllerResolver``: one inside
the HttpKernel component and the other is inside FrameworkBundle. Open both
of them. The one in FrameworkBundle extends the other. See ``BaseControllerResolver``
is the one that lives in the component::

    // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerResolver.php
    namespace Symfony\Bundle\FrameworkBundle\Controller;

    // ...
    use Symfony\Component\HttpKernel\Controller\ControllerResolver as BaseControllerResolver;

    class ControllerResolver extends BaseControllerResolver
    {
        // ...
    }

Most functions we'll look at are in the parent class, but one is overridden
in the child class.

The Controller comes from _controller
-------------------------------------

The ``getController()`` function lives in the HttpKernel ``ControllerResolver``,
so let's find it! A controller is *any* callable function and Symfony's HttpKernel
doesn't care if that's an anonymous function or whether a method inside of
an object. A lot of the code you'll see here is to support this very important
fact.

Ok, this cool! What's the first thing this function does? It goes out to
the ``request->attributes`` and looks for that ``_controller`` key::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    public function getController(Request $request)
    {
        if (!$controller = $request->attributes->get('_controller')) {
            // log a message ...

            return false;
        }

        // ...
    }

Ah, hah! So why do we use ``_controller`` in our Yaml routes? And why do
annotation routes create a route with ``_controller`` behind the scenes?
Simply because, *this* line looks for that key. And if it doesn't find one,
this function doesn't do anything: it panics and exits immediately.

Is the _controller Already a Callable?
--------------------------------------

Most of the rest of the code is basically trying to figure out: "Hey, is
the ``_controller`` key in the request attributes maybe *already* a callable
function?" In our case it's not, and let's dump() it to get a reminder of
what it looks like at this point::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    public function getController(Request $request)
    {
        if (!$controller = $request->attributes->get('_controller')) {
            // log a message ...

            return false;
        }

        dump($controller);die;
        // ...
    }

Go back to the homepage, refresh!

.. code-block:: text

    (string) 'AppBundle\Controller\DinosaurController::indexAction'

*Our* controller is this string, not technically a *callable* function yet,
even though it looks like a function name. A variable is callable if you can
invoke it via the ``call_user_func`` function.

But in other circumstances, the controller might already be callable. Silex
is a *perfect* example! There, your controllers are anonymous functions, and
behind the scenes, the function is set on the ``_controller`` key of
the route::

    // example silex app
    $app = new Silex\Application();
    $app->get('/dinosaurs/{id}', function($id) {
        return 'ROAR Dinosaur #'.$id;
    });

    // this is what happens in RouterListener
    $request->attributes->add(array(
        '_controller' => function($id) {
            return 'ROAR Dinosaur #'.$id;
        },
        'id' => 22,
    ));

So for Silex, ``_controller`` *is* callable, and so it would exit earlier
in this process.

Transforming _controller into a Callable
----------------------------------------

But in the Symfony Framework, we don't exit early. Instead, we fall down
into the ``createController`` function::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    public function getController(Request $request)
    {
        // ...

        $callable = $this->createController($controller);
        // ..

        return $callable;
    }

This is overridden in the child ``ControllerResolver``, so switch to the
one that's in the ``FrameworkBundle``. And there's our function!

.. code-block:: php

    // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerResolver.php
    // ...

    protected function createController($controller)
    {
        if (false === strpos($controller, '::')) {
            // ...
        }

        // ...
    }

Ah, this is awesome again! Look at the first part: it looks to see if your
controller already has a ``::`` syntax in the middle of it. And if you *don't*
you fall into this first block.

Transforming AppBundle:Default:index
------------------------------------

Remember that ``AppBundle:Default:index``
syntax you use in Yaml routing? That's handled here. The ``$this->parser``
you see on line 60 is responsible for transforming that 3-part syntax and
into the longer class name ``::`` method name that *our's* already has::

    // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerResolver.php
    // ...

    protected function createController($controller)
    {
        if (false === strpos($controller, '::')) {
            // ...

            // controller in the a:b:c notation then
            $controller = $this->parser->parse($controller);

            // ...
        }
        // ...
    }

.. tip::

    In reality, there is a similar layer that runs during the process of
    compiling routes that converts the ``AppBundle:Default:index`` syntax
    to ``AppBundle\Controller\DefaultController::indexAction``. But the idea
    is still the same - this just makes runtime performance a bit better.

The Controller as a Service Syntax
----------------------------------

The block below this - around line 63 - handles the service syntax (e.g.
``my_dinosaur_controller:indexAction``::

    // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerResolver.php
    // ...

    protected function createController($controller)
    {
        if (false === strpos($controller, '::')) {
            // ...

            // controller in the service:method notation
            list($service, $method) = explode(':', $controller, 2);

            return array($this->container->get($service), $method);

            // ...
        }
        // ...
    }

So if you decide to register your controller as a service, you have a different
syntax, and this is the logic that handles that.

Ultimately, we fall down to the bottom, and one way or another, we end up
with a string, which is the class name, ``::``, and then the method name.

Next, this splits those on the ``::`` and the strange ``list()`` function
sets the first part to a variable called ``$class`` and everything after
the ``::`` to a variable called ``$method``. I'll dump those variables to
be totally clear::

    // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerResolver.php
    // ...

    protected function createController($controller)
    {
        // ...

        list($class, $method) = explode('::', $controller, 2);
        dump($class, $method);die;
        // ...
    }

The ``list()`` function has confused me in the past, and the *real* key is
what these two new variables are set to.

At this point, it's time to see if we've messed up! Maybe this class doesn't
exist - maybe there's a typo somewhere. It gives us a nice error message
in that case.

Instantiating the Controller
----------------------------

Ready? On line 79, you can see the line that *actually* instantiates your
controller object::

    // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerResolver.php
    // ...

    protected function createController($controller)
    {
        // ...

        $controller = new $class();
        // ...
    }

We knew that it had to happen somewhere, because our methods are non-static,
and there it is. And because Symfony doesn't know anything about your ``Controller``
class, one of the rules - unless you register your controller as a service -
is that your controller class can't have any constructor arguments. Because
you can see - it just says ``new $class()``.

Injecting the Container (ContainerAwareInterface)
-------------------------------------------------

The next line is really really important to just about everything you do
every day in Symfony. It says: if your controller object implements the
``ContainerAwareInterface``, then call ``$controller->setContainer($container)``::

    // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerResolver.php
    // ...

    protected function createController($controller)
    {
        // ...

        $controller = new $class();
        if ($controller instanceof ContainerAwareInterface) {
            $controller->setContainer($this->container);
        }

        return array($controller, $method);
    }

So if I open up ``DinosaurController`` and click to open Symfony's base
``Controller``,  you'll see that it extends a ``ContainerAware`` class::

    namespace Symfony\Bundle\FrameworkBundle\Controller;

    use Symfony\Component\DependencyInjection\ContainerAware;
    // ...

    class Controller extends ContainerAware
    {
        // ...
    }

Let's click to open that. And we see that *it* implements the ``ContainerAwareInterface``::

    // vendor/symfony/symfony/src/Symfony/Component/DependencyInjection/ContainerAware.php
    namespace Symfony\Component\DependencyInjection;

    abstract class ContainerAware implements ContainerAwareInterface
    {
        protected $container;

        public function setContainer(ContainerInterface $container = null)
        {
            $this->container = $container;
        }
    }

So if our controller extends Symfony's base ``Controller``, we automatically
implement that interface. Because of that, the ``ControllerResolver`` *does*
call ``setContainer`` on our controller class, which is this function here.
And what does it do? It sets that on a protected ``$container`` property.
And *this* is the reason why in any controller function, we can say
``$this->container->get()`` and then get out whatever service we want::

    public function indexAction()
    {
        $this->container->get('logger')->alert('DINOS, RUN!');
    }

If, for some reasons, you didn't want to extend Symfony's base ``Controller``,
but still wanted access to the container, that would be fine: you'd just
need to implement that ``ContainerAwareInterface`` and then have, maybe,
a similar ``setContainer`` method that sets it on a ``$container`` property.

Back in ``ControllerResolver``, we now have a ``$controller`` object, we
have the ``$method`` that's going to be called on it, and it returns an array
with those two things::

    // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerResolver.php
    // ...

    protected function createController($controller)
    {
        // ...

        $controller = new $class();
        if ($controller instanceof ContainerAwareInterface) {
            $controller->setContainer($this->container);
        }

        return array($controller, $method);
    }

This is a "callable" format syntax in PHP. This ultimately goes back to the
other ``ControllerResolver`` and is returned all the way back to ``HttpKernel::handleRaw()``.

Close the FrameworkBundle ``ControllerResolver`` because we're done with it,
but leave the other one open. Now, ``$controller`` is *some* callable function::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...

        // load controller
        if (false === $controller = $this->resolver->getController($request)) {
            throw new NotFoundHttpException(sprintf('Unable to find ...'));
        }

        // the next steps...
    }

Inside of Symfony, it's going to be an object with a method name, but in
Silex it will be an anonymous function, and it really could be anything callable.
