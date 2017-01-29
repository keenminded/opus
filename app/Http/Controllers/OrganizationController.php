<?php

namespace App\Http\Controllers;

use DB;
use Mail;
use Redirect;
use App\Models\User;
use App\Models\Category;
use App\Models\ActivityLog;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class OrganizationController
 *
 * @author Zeeshan Ahmed <ziishaned@gmail.com>
 * @package App\Http\Controllers
 */
class OrganizationController extends Controller
{
    private $request;

    private $organization;

    private $user;

    private $activity;

    private $category;

    public function __construct(Request $request, 
                                Organization $organization, 
                                User $user, 
                                ActivityLog $activity,
                                Category $category)
    {
        $this->user         = $user;
        $this->request      = $request;
        $this->category     = $category;
        $this->activity     = $activity;
        $this->organization = $organization;
    }

    /**
     * Get all organization.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function index()
    {
        return $this->organization->getOrganizations();
    }

    /**
     * Get mem
     *
     * @param $organizationSlug
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getMembers($organizationSlug)
    {
        $organization        = $this->organization->getOrganization($organizationSlug);
        $organizationMembers = $this->organization->getMembers($organization);
        return view('organization.members', compact('organization', 'organizationMembers'));
    }

    /**
     * Get the create organization view.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create($step)
    {
        return view('organization.create.'.$step, compact('step'));
    }

    public function join($step)
    {
        return view('organization.join.'.$step, compact('step'));
    }

    /**
     * Creates a new organization.
     *
     * @return mixed
     */
    public function store($step)
    {
        switch ($step)
        {
            case 1:
                $rules = ['email' => 'required|email'];
                break;
            case 2:
                $rules = [
                    'validation_key' => 'required',
                ];
                break;
            case 3:
                $rules = [
                    'first_name' => 'required|max:15',
                    'last_name' => 'required|max:15',
                    'password' => 'required|min:6|confirmed',
                ];
                break;
            case 4:
                $rules = [
                    'organization_name' => 'required|unique:organization,name',
                ];
                break;
            default:
                abort(404);
        }

        $this->validate($this->request, $rules);

        switch ($step)
        {
            case 1:
                $validation_key = mt_rand(100000, 999999);
                Session::put('email', $this->request->get('email'));
                Session::put('validation_key', $validation_key);

                // $title = "Validate your email";

                // Mail::send('emails.email-validation', ['title' => $title, 'validation_code' => $validation_key], function ($message)
                // {
                //     $message->from('wiki@info.com', 'Wiki');

                //     $message->to($this->request->get('email'));

                // });
                break;
            case 2:
                if($this->request->get('validation_key') == Session::get('validation_key')) {
                    break;
                }
                return redirect()->back()->withErrors([
                    'validation_key' => 'Validation key mismatch.'
                ]);
                break;
            case 3:
                Session::put('first_name', $this->request->get('first_name'));
                Session::put('last_name', $this->request->get('last_name'));
                Session::put('password', $this->request->get('password'));
                break;
            case 4:
                $userInfo = [
                    'first_name' => Session::get('first_name'),
                    'last_name' => Session::get('last_name'),
                    'password' => Session::get('password'),
                    'email' => Session::get('email'),
                ];
                $user = (new \App\Models\User)->createUser($userInfo);
                
                $organizationData = [
                    'organization_name' => $this->request->get('organization_name'),
                    'description' => $this->request->get('description'),
                    'user_id' => $user->id,
                ];
                $organization = $this->organization->postOrganization($organizationData);

                $categories = [
                    [
                        'name' => 'Engineering',
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                    ],
                    [
                        'name' => 'New Employee Onboarding',
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                    ],
                    [
                        'name' => 'Marketing',
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                    ],
                    [
                        'name' => 'Product',
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                    ],
                    [
                        'name' => 'Human Resuorces',
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                    ],
                    [
                        'name' => 'Sales',
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                    ],
                ]; 
                foreach ($categories as $category) {
                    $this->category->create($category);
                }

                break;
            default:
                abort(404);
        }

        if ($step == 4) {
            return redirect()->route('home')->with([
                                            'alert'      => 'Organization created successfully. Now sign in to your organization!',
                                            'alert_type' => 'success'
                                        ]);
        }

        return redirect()->action('OrganizationController@create', ['step' => $step+1]);
    }

    public function isContentTypeJson()
    {
        return $this->request->header('content-type') == 'application/json'; 
    }

    public function getCategories($organizationSlug) 
    {
        $organization = $this->organization->getOrganization($organizationSlug);
        
        if($this->isContentTypeJson()) {
            $wikis = [];

            foreach($organization->wikis as $key => $wiki) {
                
                if(count($wikis) >= 5) {
                    break;
                }

                $wikis[] = [
                    'url'  => route('wikis.show', [$organization->slug, $wiki->slug]),
                    'name' => $wiki->name
                ];
            }

            return $wikis;
        }

        $categories = $this->category->where('organization_id', '=', $organization->id)->with(['wikis'])->get();

        return view('organization.category', compact('organization', 'categories'));
    }

    public function getUserContributedWikis($organizationSlug) 
    {
        $organization = $this->organization->getOrganization($organizationSlug);
        return view('organization.wikis.user-contributions', compact('organization'));
    }

    /**
     * Get the edit organization view.
     *
     * @param integer $id
     */
    public function edit($id)
    {

    }

    /**
     * This function updates organization data.
     *
     * @param  integer $id
     * @return mixed
     */
    public function update($id)
    {
        $this->validate($this->request, Organization::ORGANIZATION_RULES);
        $updated = $this->organization->updateOrganization($id, $this->request->get('organization_name'));
        if($updated) {
            return response()->json([
                'message' => 'Organization successfully updated.'
            ], Response::HTTP_OK);
        }
        return response()->json([
            'message' => 'Resource not found.'
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Deletes an organization.
     *
     * @param  integer $id
     * @return mixed
     */
    public function destroy($id)
    {
        $organizationDeleted = $this->organization->deleteOrganization($id);
        if($organizationDeleted) {
            return redirect()->route('dashboard')->with([
                'alert' => 'Organization successfully deleted.',
                'alert_type' => 'success'
            ]);
        }
        return response()->json([
            'message' => 'We are having some issues.'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function signin($step) 
    {
        return view('organization.signin.'.$step, compact('step'));
    }

    public function postJoin($step)
    {
        $validationMessage = [];
        switch ($step)
        {
            case 1:
                $rules = [
                    'organization_name' => 'required|exists:organization,name',
                ];
                $validationMessage = [
                    'exists' => 'Specified organization does\'t exists.'
                ];
                break;
            case 2:
                $rules = [
                    'email' => 'required|organization_has_email|email',
                    'password' => 'required|confirmed',
                ];
                break;
            default:
                abort(404);
        }

        $this->validate($this->request, $rules, $validationMessage);

        switch ($step)
        {
            case 1:
                Session::put('organization_name', $this->request->get('organization_name'));
                break;
            case 2:
                break;
            default:
                abort(404);
        }

        if ($step == 2) {
            $userInfo = [
                'email' => $this->request->get('email'),
                'password' => $this->request->get('password'),
                'organization' => Session::get('organization_name'),
            ];
            
            dd('');

            return redirect()->back()->with([
                                        'alert'      => 'Email or password is not valid.',
                                        'alert_type' => 'danger'
                                    ]);
        }

        return redirect()->action('OrganizationController@join', ['step' => $step+1]);
    }

    /**
     * Creates a new organization.
     *
     * @return mixed
     */
    public function postSignin($step)
    {
        $validationMessage = [];
        switch ($step)
        {
            case 1:
                $rules = [
                    'organization_name' => 'required|exists:organization,name',
                ];
                $validationMessage = [
                    'exists' => 'Specified organization does\'t exists.'
                ];
                break;
            case 2:
                $rules = [
                    'email' => 'required|email',
                    'password' => 'required',
                ];
                break;
            default:
                abort(404);
        }

        $this->validate($this->request, $rules, $validationMessage);

        switch ($step)
        {
            case 1:
                Session::put('organization_name', $this->request->get('organization_name'));
                break;
            case 2:
                break;
            default:
                abort(404);
        }

        if ($step == 2) {
            $userInfo = [
                'email' => $this->request->get('email'),
                'password' => $this->request->get('password'),
                'organization' => Session::get('organization_name'),
            ];
            
            $user = $this->user->validate($userInfo);

            if($user) {
                Auth::login($user, $this->request->get('remember'));

                // Set the cookie having organization slug
                setcookie('organization_slug', $user->organization->slug, time() + (86400 * 30), "/");

                return redirect()->route('dashboard', [$user->organization->slug, ]);
            }


            return redirect()->back()->with([
                                        'alert'      => 'Email or password is not valid.',
                                        'alert_type' => 'danger'
                                    ]);
        }

        return redirect()->action('OrganizationController@signin', ['step' => $step+1]);
    }

    public function getActivity($organizationSlug) 
    {
        $organization = $this->organization->getOrganization($organizationSlug);   
        $activities   = $this->activity->getOrganizationActivity($organization->id);
        return view('organization.activity', compact('activities', 'organization'));        
    }

    public function getUserActivity($organizationSlug) 
    {
        $organization = $this->organization->getOrganization($organizationSlug);   
        $activities   = $this->activity->getUserActivity(Auth::user()->id, $organization->id);
        return view('organization.user-activity', compact('activities', 'organization'));   
    }

    public function inviteUsers($organizationSlug) 
    {
        $organization = $this->organization->getOrganization($organizationSlug);   
        return view('organization.users.invite', compact('organization'));
    }
}
