What about Sub Requests?
========================

Have you ever been in a Twig template, and you suddenly need access to a
variable you don't have? Open up ``base.html.twig``, and let's pretend
that we want to render the latest tweets from our Twitter account at the
bottom.

Since there's no *one* controller that fuels this template, there's no way
for us to pass the latest tweets to this template. When you're in this spot,
there are 2 fixes. First, you could create a new Twig function in a Twig
extension. That's usually the best approach. The second option is with the
Twig ``render()`` function, which is our gateway to sub requests! This will
also let you cache this chunk of HTML, but we'll talk about that another time.

When we use ``render(controller('...'))``, this lets us execute a totally
different controller. Let's render a new controller called ``_latestTweetsAction``
in our same controller class:

.. code-block:: html+jinja

    {# app/Resources/views/base.html.twig #}
    {# ... #}
    
    {{ render(controller('AppBundle:Dinosaur:_latestTweets')) }}

I put the underscore in front of the name just as a reminder to myself that
the controller only returns a fragment of HTML, not a full page.

In the controller, create the ``public function _latestTweetsAction``. I
don't *really* want to bother with Twitter's API right this second, so I'll
copy in some fake tweets instead. Now let's render a template and pass those
in::

    // src/AppBundle/Controller/DinosaurController.php
    // ...

    public function _latestTweetsAction()
    {
        $tweets = [
            'Dinosaurs can have existential crises too you know.',
            'Eating lollipops... ',
            'Rock climbing... '
        ];

        return $this->render('dinosaurs/_latestTweets.html.twig', [
            'tweets' => $tweets
        ]);
    }

Yea, this is only going to return a page fragment, we still *always* return a
Response object from a controller. And that's what we're doing here. Now,
let's create this template, ``_latestTweets.html.twig``. I'll just paste
some code in that loops over these tweets and puts them in a list.

.. code-block:: html+jinja

    {# app/Resources/views/dinosaurs/_latestTweets.html.twig #}

    <div class="navbar-left tweets">
        <p class="text-center">Tweets from T-Rex Problems</p>
        <ul>
            {% for tweet in tweets %}
                <li>{{ tweet }}</li>
            {% endfor %}
        </ul>
    </div>

Nothing scary here!

The Twig ``render()`` function will call out to that function, get the Response,
grab its content, then put it at the bottom of the page. Scroll down - there 
it is!

OMG: You just made a Sub-Request!
---------------------------------

Why did I show this? Why is this important to understanding the core? Open
up the profiler and go back to the Timeline. Hmm, it looks normal at first
and we can see where our controller and template are run. But hey, what's
that darker bar behind the template? Scroll way down to find a *second* request
called a sub-request. OMG!

The sub-request is totally independent: it goes through the entire same process
we just learned. It executes the ``kernel.request``  listeners, it dispatches
the ``kernel.controller`` event, it calls the ``_latestTweetsAction`` controller
and has ``kernel.response`` on the end. It really *is* like we are handling
two totally separate request-response cycles.

Heck, you can even click to see the profiler for *just* the sub-request.

Request Attributes in Sub-Request Controllers
---------------------------------------------

Remember that in ``UserAgentSubscriber``, we're adding ``isMac`` to the request
attributes and that means it's available as an argument to *any* controller.
That's no different with our sub request controller, since this listener
is called for that request too. To prove it, I'm going to add the ``$isMac``
argument. Let's pass this into our template::

    // src/AppBundle/Controller/DinosaurController.php
    // ... 

    public function _latestTweetsAction($isMac)
    {
        // ...

        return $this->render('dinosaurs/_latestTweets.html.twig', [
            'tweets' => $tweets,
            'isMac' => $isMac
        ]);
    }

Let's print it out to make sure it's working:

.. code-block:: html+jinja

    {# app/Resources/views/dinosaurs/_latestTweets.html.twig #}

    <p class="text-center">{{ isMac ? 'on a Mac' : 'Not on a Mac' }}</p>
    {# ... #}

When we go back and refresh we see that on the top it shows that we're on
a Mac, and on the bottom inside the Tweets area, we're on a Mac too! Yay,
no surprises!

A Disturbance in the Request
----------------------------

Here is where things get crazy. Go back to ``UserAgentSubscriber``. Let's
add an override so it's easier for us to play with this "is Mac" stuff, since
I'm pretty permanently using one.

If there's a query parameter, called ``notMac``, that's set to some value
like 1, then let's always set ``$isMac`` to false::

    // src/AppBundle/EventListener/UserAgentSubscriber.php
    // ...

    public function onKernelRequest(GetResponseEvent $event)
    {
        // ...

        $isMac = stripos($userAgent, 'Mac') !== false;
        if ($request->query->get('notMac')) {
            $isMac = false;
        }
        $request->attributes->set('isMac', $isMac);
    }

Back on the browser, when I refresh, I'm still on a Mac. But if I add a ``?notMac=1``
to the URL, it goes away. The override correctly makes it look like I'm not
on a Mac.

Now scroll down. Woh! The sub request *still* thinks we're on a Mac. Something
just short circuited in the system. But before we fix it, let's dive one
level deeper and see how sub-requests really work.
