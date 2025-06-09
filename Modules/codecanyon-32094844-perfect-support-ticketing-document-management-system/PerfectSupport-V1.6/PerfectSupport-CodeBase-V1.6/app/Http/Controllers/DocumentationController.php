<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Documentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cookie;
use App\Http\Util\CommonUtil;
use Illuminate\Support\Facades\Storage;

class DocumentationController extends Controller
{   
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    /**
     * Constructor.
     *
     * @param CommonUtil
     */
    public function __construct(CommonUtil $commonUtil)
    {
        $this->CommonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        $documentations = Documentation::with(['sections' => function($q) {
                                $q->orderBy('sort_order', 'asc');
                            }, 'sections.articles' => function($q) {
                                $q->orderBy('sort_order', 'asc');
                            }])
                            ->where('doc_type', 'doc')
                            ->latest()
                            ->get();

        return Inertia::render('Documentation/Index', compact('documentations'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {   
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        $doc_type = $request->get('doc_type');
        $parent_id = $request->get('parent_id', null);

        //parent doc title to be used for heading
        $parent_doc = Documentation::find($parent_id);

        return Inertia::render('Documentation/Create', compact('doc_type', 'parent_id', 'parent_doc'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {   

        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        $validatedData = $request->validate([
            'title' => 'required'
        ]);

        try {

            $input = $request->only(['title', 'content', 'doc_type']);
            $input['status'] = 'published';
            if (!empty($request->input('parent_id'))) {
                $input['parent_id'] = $request->input('parent_id');
            }
            $input['created_by'] = \Auth::id();

            DB::beginTransaction();

            Documentation::create($input);

            DB::commit();

            return redirect()->action('DocumentationController@index')->with('success', __('messages.success'));
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->action('DocumentationController@index')->with('error', __('messages.something_went_wrong'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {   
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }
        
        $document = Documentation::findOrFail($id);

        //parent doc title to be used for heading
        $parent_doc = Documentation::find($document->parent_id);

        $parents = [];
        if($document->doc_type == 'section') {
            $parents = Documentation::getDropdown('doc');
        } else if($document->doc_type == 'article') {
            $parents = Documentation::getDropdown('section');
        }

        return Inertia::render('Documentation/Edit', compact('document', 'parent_doc', 'parents'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        $validatedData = $request->validate([
            'title' => 'required'
        ]);

        try {

            $input = $request->only(['title', 'content', 'parent_id']);

            DB::beginTransaction();

            Documentation::where('id', $id)
                ->update($input);

            DB::commit();

            return redirect()->action('DocumentationController@index')->with('success', __('messages.success'));
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->action('DocumentationController@index')->with('error', __('messages.something_went_wrong'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            DB::beginTransaction();
            
            $document = Documentation::find($id);
            $document->delete();

            DB::commit();
            return redirect()->action('DocumentationController@index')->with('success', __('messages.success'));
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->action('DocumentationController@index')->with('error', __('messages.something_went_wrong'));
        }
    }

    public function getDocumentationIndex()
    {   
        $documentations = $this->CommonUtil->getAllDocs();
        
        return view('documentation.index')
            ->with(compact('documentations'));
    }

    public function getDocumentation(Request $request)
    {   
        $id = $this->__findIdFromGivenUrl($request->url());

        $documentation = $this->__getDocumentationForSideBar($id);

        return view('documentation.show')
            ->with(compact('documentation'));
        
    }

    public function getSection(Request $request)
    {
        $id = $this->__findIdFromGivenUrl($request->url());

        $section = Documentation::where('doc_type', 'section')
                    ->findOrFail($id);

        $documentation = $this->__getDocumentationForSideBar($section->parent_id);

        $current_sec_slug = $section->doc_slug;
        $active_id = $id;
        return view('documentation.section.show')
            ->with(compact('documentation', 'section', 'current_sec_slug', 'active_id'));
    }

    public function getArticle(Request $request)
    {
        $id = $this->__findIdFromGivenUrl($request->url());

        $article = Documentation::with(['section' => function($q) {
                        $q->orderBy('sort_order', 'asc');
                    }])
                    ->where('doc_type', 'article')
                    ->findOrFail($id);

        $documentation = $this->__getDocumentationForSideBar($article->section->parent_id);
        
        $current_sec_slug = $article->section->doc_slug;
        $active_id = $id;
        return view('documentation.article.show')
            ->with(compact('documentation', 'article', 'current_sec_slug', 'active_id'));
    }

    private function __findIdFromGivenUrl($url)
    {
        return last(explode('-', $url));
    }

    private function __getDocumentationForSideBar($id)
    {
        $documentation = Documentation::with(['sections' => function($q) {
                                $q->orderBy('sort_order', 'asc');
                            }, 'sections.articles' => function($q) {
                                $q->orderBy('sort_order', 'asc');
                            }])
                            ->where('doc_type', 'doc')
                            ->findOrFail($id);

        return $documentation;
    }

    public function getDocsSuggestion(Request $request)
    {   
        if ($request->ajax()) {
            try {

                $search_params = $request->get('search_params');

                $documentations = $this->CommonUtil->getDocsSuggestion($search_params);

                $html_ele = View::make('documentation.partials.docs_suggestion', compact('documentations', 'search_params'))
                            ->render();

                $output = [
                    'success' => 1,
                    'docs' => $html_ele
                ];
            } catch (Exception $e) {
                $output = [
                    'success' => 0
                ];
            }

            return $output;
        }
    }

    public function storeFeedbackForDoc(Request $request)
    {      
        if ($request->cookie($request->get('doc_id'))) {
            return back()
            ->with('status', ['success' => false, 'msg' => __('messages.you_have_already_given_feedback')]);
        }

        try {
            $doc_id = $request->get('doc_id');
            $doc_type = $request->get('doc_type');
            $feedback = $request->get('feedback');

            $doc = Documentation::where('doc_type', $doc_type)
                    ->findOrFail($doc_id);

            DB::beginTransaction();
            $doc->$feedback = $doc->$feedback + 1;
            $doc->save();
            DB::commit();

            return back()
                ->with('status', ['success' => true, 'msg' => __('messages.thanks_for_feedback')])
                ->withCookie(cookie()->forever($doc_id, $feedback));

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);   
        }
    }

    /**
     * Upload images for doc
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $file = $request->file('file');
            //Generate a unique name for the file.
            $file_name = str_replace(' ', '_', time().'_'.$file->getClientOriginalName());
            $path = $file->storeAs(config('constants.doc_img_path'), $file_name);

            $output = [
                'success' => true,
                'msg' => __('messages.success'),
                'location' => Storage::url($path)
            ];
        } catch (Exception $e) {

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong')
            ];
        }

        return json_encode($output);
    }

    public function updateDocSortOrder(Request $request)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $ids = $request->input('ids');

            if(!empty($ids)) {
                foreach($ids as $key => $id) {
                    Documentation::where('id', $id)
                        ->update(['sort_order' => ++$key]);
                }
            }

            $output = [
                'success' => true,
                'msg' => __('messages.success')
            ];
        } catch (\Exception $e) {
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong')
            ];
        }

        return $output;
    }
}
