import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import matrix from './matrix'
/**
* @see \App\Http\Controllers\RpsAssessmentController::store
 * @see app/Http/Controllers/RpsAssessmentController.php:14
 * @route '/rps/{rps}/assessments'
 */
export const store = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/rps/{rps}/assessments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAssessmentController::store
 * @see app/Http/Controllers/RpsAssessmentController.php:14
 * @route '/rps/{rps}/assessments'
 */
store.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return store.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAssessmentController::store
 * @see app/Http/Controllers/RpsAssessmentController.php:14
 * @route '/rps/{rps}/assessments'
 */
store.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAssessmentController::store
 * @see app/Http/Controllers/RpsAssessmentController.php:14
 * @route '/rps/{rps}/assessments'
 */
    const storeForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAssessmentController::store
 * @see app/Http/Controllers/RpsAssessmentController.php:14
 * @route '/rps/{rps}/assessments'
 */
        storeForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:59
 * @route '/rps/{rps}/assessments/{assessment}'
 */
export const update = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/assessments/{assessment}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:59
 * @route '/rps/{rps}/assessments/{assessment}'
 */
update.url = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    assessment: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                assessment: args.assessment,
                }

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{assessment}', parsedArgs.assessment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:59
 * @route '/rps/{rps}/assessments/{assessment}'
 */
update.put = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:59
 * @route '/rps/{rps}/assessments/{assessment}'
 */
    const updateForm = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:59
 * @route '/rps/{rps}/assessments/{assessment}'
 */
        updateForm.put = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\RpsAssessmentController::destroy
 * @see app/Http/Controllers/RpsAssessmentController.php:190
 * @route '/rps/{rps}/assessments/{assessment}'
 */
export const destroy = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/assessments/{assessment}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\RpsAssessmentController::destroy
 * @see app/Http/Controllers/RpsAssessmentController.php:190
 * @route '/rps/{rps}/assessments/{assessment}'
 */
destroy.url = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    assessment: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                assessment: args.assessment,
                }

    return destroy.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{assessment}', parsedArgs.assessment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAssessmentController::destroy
 * @see app/Http/Controllers/RpsAssessmentController.php:190
 * @route '/rps/{rps}/assessments/{assessment}'
 */
destroy.delete = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\RpsAssessmentController::destroy
 * @see app/Http/Controllers/RpsAssessmentController.php:190
 * @route '/rps/{rps}/assessments/{assessment}'
 */
    const destroyForm = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAssessmentController::destroy
 * @see app/Http/Controllers/RpsAssessmentController.php:190
 * @route '/rps/{rps}/assessments/{assessment}'
 */
        destroyForm.delete = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const assessments = {
    store: Object.assign(store, store),
update: Object.assign(update, update),
matrix: Object.assign(matrix, matrix),
destroy: Object.assign(destroy, destroy),
}

export default assessments